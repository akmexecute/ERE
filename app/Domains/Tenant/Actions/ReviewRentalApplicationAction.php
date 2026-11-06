<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\RentalApplicationHistory;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class ReviewRentalApplicationAction
{
    public function execute(
        User $actor,
        RentalApplication $application,
        string $decision, // approve, reject, document_review, withdraw
        ?string $notes = null,
        ?string $rejectionReason = null
    ): RentalApplication {
        // Withdrawal is initiated by the tenant
        if ($decision === 'withdraw') {
            if ($actor->id !== $application->tenant_id && ! $actor->hasRole('super_admin')) {
                throw new AuthorizationException('Only the applicant can withdraw this application.');
            }

            if ($application->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot withdraw an application that has already been finalized.'],
                ]);
            }

            $fromStatus = is_object($application->status) ? $application->status->value : (string) $application->status;

            $application->update([
                'status' => RentalApplicationStatus::Withdrawn,
                'review_notes' => $notes ?? 'Withdrawn by applicant.',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            RentalApplicationHistory::query()->create([
                'rental_application_id' => $application->id,
                'from_status' => $fromStatus,
                'to_status' => RentalApplicationStatus::Withdrawn->value,
                'actor_id' => $actor->id,
                'notes' => $notes ?? 'Withdrawn by applicant.',
                'created_at' => now(),
            ]);

            return $application->fresh();
        }

        // Approval, rejection, and document review require landlord or admin authorization
        $isLandlord = ($actor->id === $application->landlord_id);
        $isCompanyAuthorized = ($application->company_id && $actor->company_id === $application->company_id);
        $isStaff = $actor->hasAnyRole(['property_admin', 'platform_admin', 'super_admin']);

        if (! $isLandlord && ! $isCompanyAuthorized && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to review this rental application.');
        }

        if ($application->status->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ['Cannot review an application that has already been finalized.'],
            ]);
        }

        $targetStatus = match ($decision) {
            'approve' => RentalApplicationStatus::Approved,
            'reject' => RentalApplicationStatus::Rejected,
            'document_review' => RentalApplicationStatus::DocumentReview,
            default => throw ValidationException::withMessages([
                'decision' => ["Invalid application decision '{$decision}'."],
            ]),
        };

        if ($targetStatus === RentalApplicationStatus::Rejected && empty($rejectionReason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => ['A rejection reason is mandatory when rejecting a rental application.'],
            ]);
        }

        $fromStatus = is_object($application->status) ? $application->status->value : (string) $application->status;

        $application->update([
            'status' => $targetStatus,
            'review_notes' => $notes,
            'rejection_reason' => $rejectionReason,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        RentalApplicationHistory::query()->create([
            'rental_application_id' => $application->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus->value,
            'actor_id' => $actor->id,
            'notes' => $notes ?? ($targetStatus === RentalApplicationStatus::Rejected ? $rejectionReason : null),
            'created_at' => now(),
        ]);

        return $application->fresh();
    }
}
