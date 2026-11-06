<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Actions\SubmitNationalIdVerificationAction;
use App\Domains\Identity\Contracts\FaydaProviderInterface;
use App\Domains\Identity\Exceptions\FaydaServiceUnavailableException;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\ReviewVerificationRequestAction;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FaydaVerificationService
{
    public function __construct(
        private ?FaydaProviderInterface $provider = null,
        private ?SubmitNationalIdVerificationAction $submitAction = null,
        private ?ReviewVerificationRequestAction $reviewAction = null
    ) {
        $this->provider = $provider ?? app(OfficialFaydaApiProvider::class);
        $this->submitAction = $submitAction ?? app(SubmitNationalIdVerificationAction::class);
        $this->reviewAction = $reviewAction ?? app(ReviewVerificationRequestAction::class);
    }

    /**
     * Report real connection status of the official Fayda API.
     *
     * @return array{
     *   is_connected: bool,
     *   provider_name: string,
     *   active_mode: string,
     *   manual_review_operational: bool,
     *   status_notice: string
     * }
     */
    public function getProviderStatus(): array
    {
        $connected = $this->provider->isConfigured();

        return [
            'is_connected' => $connected,
            'provider_name' => $this->provider->getProviderName(),
            'active_mode' => $connected ? 'official_fayda_api' : 'manual_review',
            'manual_review_operational' => true,
            'status_notice' => $connected
                ? 'Official Fayda API is connected and active.'
                : 'Official Fayda API is not connected. Internal compliance manual review is operational.',
        ];
    }

    /**
     * Submit an identity verification request for internal manual review by authorized ERE compliance officers.
     */
    public function submitForManualReview(
        User $user,
        string $nationalId,
        string $idType,
        UploadedFile $front,
        ?UploadedFile $back = null
    ): Verification {
        $updatedUser = $this->submitAction->execute(
            user: $user,
            nationalIdNumber: $nationalId,
            nationalIdType: $idType,
            frontImage: $front,
            backImage: $back
        );

        $ref = 'VER-MAN-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

        /** @var Verification $verification */
        $verification = Verification::query()
            ->where('verifiable_type', User::class)
            ->where('verifiable_id', $updatedUser->id)
            ->latest('id')
            ->first();

        if ($verification) {
            $verification->update([
                'verification_method' => 'manual_review',
                'verification_reference' => $ref,
                'evidence_type' => $idType,
                'status' => 'pending',
            ]);
        }

        return $verification;
    }

    /**
     * Attempt automated official Fayda API verification.
     * Throws FaydaServiceUnavailableException if the official API is unconfigured or unavailable.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function verifyViaOfficialApi(User $user, string $nationalId, array $attributes = []): Verification
    {
        if (! $this->provider->isConfigured()) {
            throw new FaydaServiceUnavailableException(
                'Official Fayda API is not connected. Automated verification is currently disabled in favor of ERE manual compliance review.'
            );
        }

        $result = $this->provider->verifyNationalId($nationalId, $attributes);

        return DB::transaction(function () use ($user, $nationalId, $result): Verification {
            $ref = 'VER-FAYDA-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            $verification = Verification::query()->create([
                'uuid' => (string) Str::uuid(),
                'verifiable_type' => User::class,
                'verifiable_id' => $user->id,
                'requested_by_user_id' => $user->id,
                'requested_level' => 1,
                'approved_level' => VerificationLevel::SellerOrCompanyVerified,
                'badge_code' => 'verified_seller',
                'status' => 'approved',
                'verification_method' => 'official_fayda_api',
                'verification_reference' => $ref,
                'provider_reference' => $result['reference'],
                'evidence_type' => 'fayda_national_id',
                'reviewed_at' => now(),
            ]);

            $user->update([
                'national_id_number' => $nationalId,
                'national_id_type' => 'fayda',
                'verification_level' => 1,
                'identity_verified_at' => now(),
            ]);

            return $verification;
        });
    }

    /**
     * Standard human-readable badge text describing how the identity was established.
     */
    public static function formatVerificationBadge(string $method, string $status = 'approved'): string
    {
        if ($status !== 'approved' && $status !== 'manually_verified' && $status !== 'officially_api_verified') {
            return 'Pending Verification';
        }

        return match ($method) {
            'official_fayda_api' => 'Officially verified through Fayda',
            default => 'Internally verified by ERE',
        };
    }
}

