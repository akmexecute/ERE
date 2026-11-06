<?php

declare(strict_types=1);

namespace App\Domains\Property\Actions;

use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class VerifyPropertyOwnershipAndAuthorityAction
{
    private const ALLOWED_EVIDENCE = [
        'title_deed',
        'leasehold_certificate',
        'power_of_attorney',
        'mandate_contract',
        'court_letter',
        'developer_construction_permit',
    ];

    private const ALLOWED_SCOPES = [
        'direct_owner',
        'mandated_agent',
        'company_representative',
        'heir_executor',
        'developer_principal',
    ];

    private const VALID_DECISIONS = [
        'approved',
        'rejected',
        'more_info_required',
    ];

    /**
     * Authoritative separation of Property Ownership / Selling Authority verification from Identity.
     * Enforces independent review, self-approval prevention, and statutory legal disclaimers.
     *
     * @throws ValidationException
     */
    public function execute(
        User $officer,
        Property $property,
        string $decision,
        string $evidenceType,
        string $authorityScope,
        ?int $authorizedSellerUserId = null,
        ?string $validUntil = null,
        string $notes = ''
    ): Property {
        if (! in_array($decision, self::VALID_DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid decision '{$decision}'.");
        }

        if (! in_array($evidenceType, self::ALLOWED_EVIDENCE, true)) {
            throw ValidationException::withMessages([
                'evidence_type' => ["Invalid ownership evidence type '{$evidenceType}'."],
            ]);
        }

        if (! in_array($authorityScope, self::ALLOWED_SCOPES, true)) {
            throw ValidationException::withMessages([
                'authority_scope' => ["Invalid authority scope '{$authorityScope}'."],
            ]);
        }

        if (in_array($decision, ['rejected', 'more_info_required'], true) && trim($notes) === '') {
            throw ValidationException::withMessages([
                'notes' => ['An explanation reason is required when rejecting or requesting additional information.'],
            ]);
        }

        // 1. Strict Self-Approval Prevention
        if ((int) $officer->id === (int) $property->owner_id ||
            ($authorizedSellerUserId !== null && (int) $officer->id === (int) $authorizedSellerUserId)) {
            throw ValidationException::withMessages([
                'officer' => ['Self-approval of property ownership or selling authority is strictly forbidden. An independent authorized officer must review this property.'],
            ]);
        }

        // 2. Officer authorization check
        $canApprove = (method_exists($officer, 'hasPermission') && $officer->hasPermission('verification.approve'))
            || (method_exists($officer, 'hasAnyRole') && $officer->hasAnyRole([
                'admin', 'system_admin', 'super_admin', 'platform_admin', 'verification_officer', 'property_admin',
            ]));

        if (! $canApprove) {
            throw ValidationException::withMessages([
                'officer' => ['You do not have permission to verify property ownership or selling authority.'],
            ]);
        }

        return DB::transaction(function () use (
            $officer,
            $property,
            $decision,
            $evidenceType,
            $authorityScope,
            $authorizedSellerUserId,
            $validUntil,
            $notes
        ): Property {
            /** @var Property $lockedProperty */
            $lockedProperty = Property::query()->where('id', $property->id)->lockForUpdate()->firstOrFail();

            if ($decision === 'approved') {
                $status = 'authority_verified';
                $legalDisclaimer = 'Internal platform document review only; not official government land registry certification.';

                $lockedProperty->update([
                    'ownership_status' => $status,
                    'ownership_evidence_type' => $evidenceType,
                    'ownership_relationship' => $authorityScope,
                    'ownership_verified_at' => now(),
                    'ownership_verified_by_user_id' => $officer->id,
                    'authorized_seller_user_id' => $authorizedSellerUserId ?? $lockedProperty->owner_id,
                    'authority_valid_until' => $validUntil,
                    'ownership_legal_disclaimer' => $legalDisclaimer,
                    'verification_level' => max($lockedProperty->verification_level, 3),
                ]);

                // Record polymorphic verification
                $verRef = 'VER-PROP-' . date('Y') . '-' . str_pad((string) $lockedProperty->id, 6, '0', STR_PAD_LEFT);
                Verification::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'verifiable_type' => Property::class,
                    'verifiable_id' => $lockedProperty->id,
                    'requested_by_user_id' => $lockedProperty->owner_id,
                    'requested_level' => 3,
                    'approved_level' => VerificationLevel::ProfessionalReview,
                    'badge_code' => 'professionally_reviewed',
                    'status' => 'approved',
                    'verification_method' => 'manual_review',
                    'verification_reference' => $verRef,
                    'evidence_type' => $evidenceType,
                    'authority_scope' => $authorityScope,
                    'reviewed_by_user_id' => $officer->id,
                    'reviewed_at' => now(),
                    'internal_officer_notes' => $notes ?: 'Property ownership and legal selling authority verified by compliance officer.',
                ]);
            } else {
                $status = $decision === 'rejected' ? 'rejected' : 'pending_review';

                $lockedProperty->update([
                    'ownership_status' => $status,
                    'ownership_evidence_type' => $evidenceType,
                    'ownership_relationship' => $authorityScope,
                    'ownership_verified_at' => null,
                    'ownership_verified_by_user_id' => $officer->id,
                    'ownership_legal_disclaimer' => null,
                ]);

                $verRef = 'VER-PROP-' . date('Y') . '-' . str_pad((string) $lockedProperty->id, 6, '0', STR_PAD_LEFT);
                Verification::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'verifiable_type' => Property::class,
                    'verifiable_id' => $lockedProperty->id,
                    'requested_by_user_id' => $lockedProperty->owner_id,
                    'requested_level' => 3,
                    'approved_level' => VerificationLevel::Unverified,
                    'badge_code' => null,
                    'status' => $status,
                    'verification_method' => 'manual_review',
                    'verification_reference' => $verRef,
                    'evidence_type' => $evidenceType,
                    'authority_scope' => $authorityScope,
                    'reviewed_by_user_id' => $officer->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => $notes,
                ]);
            }

            // Write immutable audit log
            if (DB::getSchemaBuilder()->hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $officer->id,
                    'action' => 'property.ownership_evaluated',
                    'auditable_type' => Property::class,
                    'auditable_id' => (string) $lockedProperty->id,
                    'new_values' => json_encode([
                        'decision' => $decision,
                        'evidence_type' => $evidenceType,
                        'authority_scope' => $authorityScope,
                        'notes' => $notes,
                    ]),
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => now(),
                ]);
            }

            return $lockedProperty->fresh(['ownershipVerifier', 'authorizedSeller']);
        });
    }
}
