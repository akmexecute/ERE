<?php

declare(strict_types=1);

namespace App\Domains\Verification\Actions;

use App\Domains\User\Models\User;
use App\Domains\Verification\Enums\VerificationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReviewVerificationRequestAction
{
    public function execute(
        User $officer,
        int $verificationId,
        string $decision, // approved, rejected, more_info_required
        VerificationLevel $grantedLevel,
        string $reason,
        ?string $badgeCode = null
    ): void {
        if (! $officer->hasPermission('verification.review')) {
            throw ValidationException::withMessages([
                'officer' => ['You do not have permission to review verification requests.'],
            ]);
        }

        if ($decision === 'approved' && ! $officer->hasPermission('verification.approve')) {
            throw ValidationException::withMessages([
                'officer' => ['You do not have permission to approve verification levels.'],
            ]);
        }

        DB::transaction(function () use ($officer, $verificationId, $decision, $grantedLevel, $reason, $badgeCode): void {
            $verification = DB::table('verifications')
                ->where('id', $verificationId)
                ->lockForUpdate()
                ->firstOrFail();

            $newLevel = $decision === 'approved' ? $grantedLevel->value : (int) $verification->approved_level;

            DB::table('verifications')->where('id', $verificationId)->update([
                'status' => $decision,
                'approved_level' => $newLevel,
                'badge_code' => $decision === 'approved' ? ($badgeCode ?? 'verified_seller') : $verification->badge_code,
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

            // Propagate approved verification_level to the target entity (User, Company, or Property)
            if ($decision === 'approved') {
                /** @var class-string<Model> $targetClass */
                $targetClass = $verification->verifiable_type;
                if (class_exists($targetClass)) {
                    $targetModel = $targetClass::query()->find($verification->verifiable_id);
                    if ($targetModel && isset($targetModel->verification_level)) {
                        if (method_exists($targetModel, 'withAuditReason')) {
                            $targetModel->withAuditReason("Verification #{$verificationId} approved: {$reason}");
                        }
                        $targetModel->update([
                            'verification_level' => max((int) $targetModel->verification_level, $newLevel),
                        ]);
                    }
                }
            }
        });
    }
}
