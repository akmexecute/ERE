<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Actions;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Moderation\Models\FraudSignal;
use App\Domains\Moderation\Models\ModerationAction;
use App\Domains\Moderation\Models\PropertyReport;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ModerateListingAction
{
    public const array ALLOWED_DECISIONS = ['approve', 'reject', 'suspend', 'request_correction'];

    /**
     * Atomically executes a moderation decision (approve, reject, suspend, request_correction) on a listing (SRS Section 134).
     *
     * @throws AuthorizationException
     */
    public function execute(
        User $moderator,
        Listing $listing,
        string $decision, // 'approve' | 'reject' | 'suspend' | 'request_correction'
        ?string $reasonCode = null,
        ?string $feedbackToSeller = null,
        bool $bypassSubscriptionQuota = false,
        ?string $overrideReason = null
    ): Listing {
        // Enforce moderator / administrator authorization
        if (! $this->canModerate($moderator)) {
            throw new AuthorizationException('You are not authorized to moderate listings.');
        }

        if ($bypassSubscriptionQuota) {
            if (! $this->canOverrideSubscriptionQuota($moderator)) {
                throw new AuthorizationException('You are not authorized to override subscription listing quotas.');
            }

            if (empty($overrideReason) || mb_strlen(trim($overrideReason)) < 5 || mb_strlen($overrideReason) > 500) {
                throw new InvalidArgumentException('A documented administrative override reason (5-500 characters) is required to bypass listing quotas.');
            }
        }

        if (! in_array($decision, self::ALLOWED_DECISIONS, true)) {
            throw new InvalidArgumentException("Unsupported moderation decision: {$decision}. Allowed: " . implode(', ', self::ALLOWED_DECISIONS));
        }

        return DB::transaction(function () use ($moderator, $listing, $decision, $reasonCode, $feedbackToSeller, $bypassSubscriptionQuota, $overrideReason): Listing {
            $fromStatus = $listing->status instanceof ListingStatus ? $listing->status->value : (string) $listing->status;

            if ($decision === 'approve') {
                $publisher = $listing->publishedByUser ?? \App\Domains\User\Models\User::find($listing->published_by_user_id);
                $entitlementService = app(\App\Domains\Subscription\Services\SubscriptionEntitlementService::class);

                if ($publisher && ! $bypassSubscriptionQuota) {
                    $entitlementService->validateListingPublishing($publisher, $listing);
                }

                if ($publisher && $bypassSubscriptionQuota) {
                    $ownerPlan = $entitlementService->getEffectivePlan($publisher);
                    $ownerLimit = $entitlementService->getLimit($publisher, 'property.max_active_listings');
                    $ownerActiveCount = Listing::query()
                        ->where('published_by_user_id', $publisher->id)
                        ->where('status', ListingStatus::Published->value)
                        ->whereKeyNot($listing->id)
                        ->count();

                    app(\App\Domains\Administration\Services\AuditService::class)->log(
                        action: 'listing_subscription_quota_override',
                        auditable: $listing,
                        oldValues: [
                            'listing_id' => $listing->id,
                            'listing_uuid' => $listing->uuid,
                            'listing_code' => $listing->listing_code,
                            'previous_status' => $fromStatus,
                            'listing_owner_id' => $publisher->id,
                            'owner_plan_code' => $ownerPlan?->code ?? 'free',
                            'owner_listing_limit' => $ownerLimit,
                            'owner_active_listings_count' => $ownerActiveCount,
                            'quota_exceeded' => $ownerActiveCount >= $ownerLimit,
                        ],
                        newValues: [
                            'decision' => 'approve',
                            'new_status' => ListingStatus::Published->value,
                            'override_applied' => true,
                            'override_reason' => trim($overrideReason),
                            'acting_administrator_id' => $moderator->id,
                            'acting_administrator_role' => $moderator->roles->pluck('slug')->first() ?? 'administrator',
                            'acting_administrator_permissions' => ['listings.moderate.override_subscription_quota'],
                        ],
                        reason: trim($overrideReason),
                        userId: $moderator->id
                    );
                }

                $toStatus = ListingStatus::Published->value;
                $actionType = 'approve';

                $listing->update([
                    'status' => ListingStatus::Published,
                    'published_at' => now(),
                ]);
            } elseif ($decision === 'reject') {
                if (empty($reasonCode) && empty($feedbackToSeller)) {
                    throw new InvalidArgumentException('A reason code or feedback is required to reject a listing.');
                }

                $toStatus = ListingStatus::Rejected->value;
                $actionType = 'reject';

                $listing->update([
                    'status' => ListingStatus::Rejected,
                ]);
            } elseif ($decision === 'suspend') {
                if (empty($reasonCode) && empty($feedbackToSeller)) {
                    throw new InvalidArgumentException('A reason code or feedback is required to suspend a listing.');
                }

                $toStatus = ListingStatus::Suspended->value;
                $actionType = 'suspend';

                $listing->update([
                    'status' => ListingStatus::Suspended,
                ]);
            } elseif ($decision === 'request_correction') {
                if (empty($feedbackToSeller)) {
                    throw new InvalidArgumentException('Feedback to seller is required when requesting corrections.');
                }

                $toStatus = ListingStatus::Draft->value;
                $actionType = 'request_correction';

                $listing->update([
                    'status' => ListingStatus::Draft,
                ]);
            } else {
                throw new InvalidArgumentException("Unsupported moderation decision: {$decision}");
            }

            ModerationAction::query()->create([
                'listing_id' => $listing->id,
                'moderator_id' => $moderator->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'action_type' => $actionType,
                'reason_code' => $reasonCode,
                'feedback_to_seller' => $feedbackToSeller,
                'created_at' => now(),
            ]);

            return $listing->fresh();
        });
    }

    /**
     * Resolves or dismisses an automated fraud signal with human audit notes (SRS Section 134).
     *
     * @throws AuthorizationException
     */
    public function resolveFraudSignal(
        User $moderator,
        FraudSignal $signal,
        string $resolution, // 'dismissed' | 'confirmed_fraud'
        ?string $notes = null
    ): FraudSignal {
        if (! $this->canModerate($moderator)) {
            throw new AuthorizationException('You are not authorized to moderate fraud signals.');
        }

        if (! in_array($resolution, ['dismissed', 'confirmed_fraud'], true)) {
            throw new InvalidArgumentException("Invalid fraud signal resolution: '{$resolution}'. Must be 'dismissed' or 'confirmed_fraud'.");
        }

        $evidence = $signal->evidence_json ?? [];
        if ($notes !== null && trim($notes) !== '') {
            $evidence['resolution_notes'] = trim($notes);
            $evidence['resolved_by_name'] = $moderator->name;
        }

        $signal->update([
            'status' => $resolution,
            'resolved_by' => $moderator->id,
            'resolved_at' => now(),
            'evidence_json' => $evidence,
        ]);

        return $signal->fresh();
    }

    /**
     * Reviews and updates the status of a public property report.
     *
     * @throws AuthorizationException
     */
    public function reviewPropertyReport(
        User $moderator,
        PropertyReport $report,
        string $decision, // 'investigating' | 'resolved' | 'dismissed'
        ?string $notes = null
    ): PropertyReport {
        if (! $this->canModerate($moderator)) {
            throw new AuthorizationException('You are not authorized to review property reports.');
        }

        if (! in_array($decision, ['investigating', 'resolved', 'dismissed'], true)) {
            throw new InvalidArgumentException("Invalid report review decision: '{$decision}'.");
        }

        $report->update([
            'status' => $decision,
            'reviewed_by' => $moderator->id,
            'reviewed_at' => now(),
        ]);

        return $report->fresh();
    }

    public function canModerate(User $user): bool
    {
        if (method_exists($user, 'hasPermission') && $user->hasPermission('property.approve')) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'moderator', 'system_admin', 'super_admin', 'platform_admin', 'property_admin']);
        }

        return false;
    }

    /**
     * Dedicated authorization check for bypassing seller subscription listing quotas.
     * Distinct from ordinary listing approval authority.
     */
    public function canOverrideSubscriptionQuota(User $user): bool
    {
        if (! $this->canModerate($user)) {
            return false;
        }

        if ($user->status !== 'active') {
            return false;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('listings.moderate.override_subscription_quota');
    }
}
