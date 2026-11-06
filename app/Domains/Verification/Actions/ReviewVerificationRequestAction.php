<?php

declare(strict_types=1);

namespace App\Domains\Verification\Actions;

use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ReviewVerificationRequestAction
{
    /**
     * Strict allowlist for verifiable polymorphic targets.
     * Prevents arbitrary model instantiation / injection.
     */
    private const ALLOWED_TARGETS = [
        'property' => Property::class,
        'user' => User::class,
        'company' => Company::class,
        Property::class => Property::class,
        User::class => User::class,
        Company::class => Company::class,
    ];

    /**
     * Controlled trust badge allowlist (SRS Section 23).
     */
    private const ALLOWED_BADGES = [
        'verified_seller',
        'verified_company',
        'verified_developer',
        'documents_submitted',
        'professionally_reviewed',
        'transaction_ready',
    ];

    private const VALID_DECISIONS = [
        'approved',
        'rejected',
        'more_info_required',
        'under_review',
    ];

    public function execute(
        User $officer,
        int $verificationId,
        string $decision, // approved, rejected, more_info_required, under_review
        VerificationLevel $grantedLevel = VerificationLevel::Unverified,
        string $reason = '',
        ?string $badgeCode = null
    ): void {
        if (! in_array($decision, self::VALID_DECISIONS, true)) {
            throw new InvalidArgumentException("Invalid verification decision: {$decision}.");
        }

        if (in_array($decision, ['rejected', 'more_info_required'], true) && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A valid explanation reason is required when rejecting or requesting more info.'],
            ]);
        }

        if ($badgeCode !== null && ! in_array($badgeCode, self::ALLOWED_BADGES, true)) {
            throw ValidationException::withMessages([
                'badge_code' => ['The specified badge code is not permitted.'],
            ]);
        }

        // Authorization check: explicit permission OR authorized staff role
        $canReview = (method_exists($officer, 'hasPermission') && $officer->hasPermission('verification.review'))
            || (method_exists($officer, 'hasAnyRole') && $officer->hasAnyRole([
                'admin', 'moderator', 'system_admin', 'super_admin', 'platform_admin', 'verification_officer', 'property_admin',
            ]));

        if (! $canReview) {
            throw ValidationException::withMessages([
                'officer' => ['You do not have permission to review verification requests.'],
            ]);
        }

        if ($decision === 'approved') {
            $canApprove = (method_exists($officer, 'hasPermission') && $officer->hasPermission('verification.approve'))
                || (method_exists($officer, 'hasAnyRole') && $officer->hasAnyRole([
                    'admin', 'system_admin', 'super_admin', 'platform_admin', 'verification_officer',
                ]));

            if (! $canApprove) {
                throw ValidationException::withMessages([
                    'officer' => ['You do not have permission to approve verification levels.'],
                ]);
            }
        }

        DB::transaction(function () use ($officer, $verificationId, $decision, $grantedLevel, $reason, $badgeCode): void {
            $verification = DB::table('verifications')
                ->where('id', $verificationId)
                ->lockForUpdate()
                ->firstOrFail();

            // Strict Security Rule (Section 4.3): Self-approval is strictly prohibited
            if ((int) $verification->requested_by_user_id === (int) $officer->id ||
                ($verification->verifiable_type === 'user' && (int) $verification->verifiable_id === (int) $officer->id) ||
                ($verification->verifiable_type === User::class && (int) $verification->verifiable_id === (int) $officer->id)) {
                throw ValidationException::withMessages([
                    'officer' => ['Self-approval of identity or seller verification is strictly forbidden. An independent authorized officer must review this request.'],
                ]);
            }

            $newLevel = $decision === 'approved' ? $grantedLevel->value : (int) $verification->approved_level;

            // Determine badge code
            $finalBadgeCode = $verification->badge_code;
            if ($decision === 'approved') {
                $finalBadgeCode = $badgeCode ?? match ($grantedLevel) {
                    VerificationLevel::TransactionReady => 'transaction_ready',
                    VerificationLevel::ProfessionalReview => 'professionally_reviewed',
                    VerificationLevel::DocumentsSubmitted => 'documents_submitted',
                    VerificationLevel::SellerOrCompanyVerified => 'verified_seller',
                    default => 'verified_seller',
                };
            } elseif ($decision === 'rejected' && (int) $verification->approved_level === 0) {
                $finalBadgeCode = null;
            }

            $verRef = $verification->verification_reference ?? ('VER-ERE-' . date('Y') . '-' . str_pad((string) $verificationId, 6, '0', STR_PAD_LEFT));
            $verMethod = $verification->verification_method ?? 'manual_review';

            DB::table('verifications')->where('id', $verificationId)->update([
                'status' => $decision,
                'approved_level' => $newLevel,
                'badge_code' => $finalBadgeCode,
                'verification_method' => $verMethod,
                'verification_reference' => $verRef,
                'reviewed_by_user_id' => $officer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $decision !== 'approved' ? $reason : null,
                'updated_at' => now(),
            ]);

            DB::table('verification_reviews')->insert([
                'verification_id' => $verificationId,
                'officer_id' => $officer->id,
                'from_status' => $verification->status,
                'to_status' => $decision,
                'granted_level' => $newLevel,
                'decision_reason' => $reason,
                'created_at' => now(),
            ]);

            // Propagate authoritative verification_level to the target entity (User, Company, or Property)
            $targetType = $verification->verifiable_type;
            $targetClass = self::ALLOWED_TARGETS[$targetType] ?? null;

            if ($targetClass === null) {
                throw ValidationException::withMessages([
                    'target' => ["Unsupported verification target type '{$targetType}'."],
                ]);
            }

            $targetModel = $targetClass::query()->find($verification->verifiable_id);
            if ($targetModel && isset($targetModel->verification_level)) {
                    // Authoritative Level Calculation:
                    // Find highest level among currently approved, non-expired verifications for this target
                    $authoritativeLevel = (int) (DB::table('verifications')
                        ->where('verifiable_type', $targetType)
                        ->where('verifiable_id', $verification->verifiable_id)
                        ->where('status', 'approved')
                        ->where(function ($q): void {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })
                        ->max('approved_level') ?? 0);

                    if (method_exists($targetModel, 'withAuditReason')) {
                        $targetModel->withAuditReason("Verification #{$verificationId} evaluated as {$decision}: {$reason}");
                    }

                    $modelUpdates = [
                        'verification_level' => $authoritativeLevel,
                    ];

                    if ($targetModel instanceof User) {
                        if ($decision === 'approved' && $authoritativeLevel >= 1) {
                            $modelUpdates['identity_verified_at'] = now();
                        } elseif ($decision === 'rejected' && $authoritativeLevel === 0) {
                            $modelUpdates['identity_verified_at'] = null;
                        }
                    }

                    $targetModel->update($modelUpdates);
                }
        });
    }
}
