<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\User\Models\User;
use Illuminate\Validation\ValidationException;

final class IdentityGateService
{
    /**
     * Qualifying protected transactions under ERE trust and regulatory standards.
     */
    public const ACTION_BOOK_STAY = 'book_stay';
    public const ACTION_MAKE_OFFER = 'make_offer';
    public const ACTION_RENTAL_APPLICATION = 'rental_application';
    public const ACTION_SIGN_LEASE = 'sign_lease';
    public const ACTION_RESERVE_UNIT = 'reserve_unit';
    public const ACTION_PUBLISH_LISTING = 'publish_listing';
    public const ACTION_HOST_STAY = 'host_stay';

    /**
     * Check whether the user has satisfied the National ID/Fayda, document images, and phone verification.
     *
     * @return array{
     *   allowed: bool,
     *   missing: array<string>,
     *   status: string,
     *   masked_id: ?string,
     *   requires_phone_otp: bool,
     *   requires_front_image: bool,
     *   requires_back_image: bool,
     *   requires_id_number: bool
     * }
     */
    public function check(User $user, string $action): array
    {
        $missing = [];

        // 1. Valid telephone number verified through OTP
        $phoneVerified = $user->phone_verified_at !== null;
        if (! $phoneVerified) {
            $missing[] = 'phone_verification';
        }

        $isIdentityVerified = $user->isIdentityVerified();

        // 2. National ID / Fayda identifier
        $hasIdNumber = ! empty($user->national_id_number);
        if (! $hasIdNumber && ! $isIdentityVerified) {
            $missing[] = 'national_id_number';
        }

        // 3. Front image of National ID/Fayda
        $hasFront = $user->id_front_document_id !== null;
        if (! $hasFront && ! $isIdentityVerified) {
            $missing[] = 'front_document_image';
        }

        // 4. Back image (required unless digital single-sided ID)
        $isDigitalSingleSided = in_array($user->national_id_type, ['fayda_digital_qr', 'digital_id'], true);
        $hasBack = $isDigitalSingleSided || $user->id_back_document_id !== null;
        if (! $hasBack && ! $isIdentityVerified) {
            $missing[] = 'back_document_image';
        }

        // 5. Verification approval status
        $status = 'unsubmitted';
        if ($isIdentityVerified) {
            $status = 'approved';
        } elseif ($hasIdNumber && $hasFront) {
            $status = 'pending_review';
        }

        $allowed = empty($missing);

        return [
            'allowed' => $allowed,
            'is_allowed' => $allowed,
            'missing' => $missing,
            'missing_requirement' => ! empty($missing) ? $missing[0] : null,
            'status' => $status,
            'masked_id' => $user->masked_national_id,
            'requires_phone_otp' => ! $phoneVerified,
            'requires_front_image' => ! $hasFront,
            'requires_back_image' => ! $hasBack,
            'requires_id_number' => ! $hasIdNumber,
        ];
    }

    public function requiresIdentityVerification(string $action): bool
    {
        return in_array($action, [
            self::ACTION_BOOK_STAY,
            self::ACTION_MAKE_OFFER,
            self::ACTION_RENTAL_APPLICATION,
            self::ACTION_SIGN_LEASE,
            self::ACTION_RESERVE_UNIT,
            self::ACTION_PUBLISH_LISTING,
            self::ACTION_HOST_STAY,
            'book_stay',
            'make_offer',
            'rental_application',
            'sign_lease',
            'reserve_unit',
            'publish_listings',
            'host_stay',
        ], true);
    }

    public function canPerformAction(User $user, string $action): bool
    {
        if (! $this->requiresIdentityVerification($action)) {
            return true;
        }

        return $this->check($user, $action)['allowed'];
    }

    /**
     * Enforce customer information gate before transaction initiation.
     *
     * @throws ValidationException
     */
    public function enforce(User $user, string $action): void
    {
        $result = $this->check($user, $action);

        if (! $result['allowed']) {
            $messages = [];
            if ($result['requires_phone_otp']) {
                $messages['phone'] = ['A verified telephone number via OTP is required before continuing.'];
            }
            if ($result['requires_id_number']) {
                $messages['national_id_number'] = ['A valid National ID or Fayda identification number is required.'];
            }
            if ($result['requires_front_image']) {
                $messages['id_front_image'] = ['A clear front-side photograph of your National ID or Fayda card is required.'];
            }
            if ($result['requires_back_image']) {
                $messages['id_back_image'] = ['A clear back-side photograph of your physical National ID or Fayda card is required.'];
            }

            throw ValidationException::withMessages($messages ?: [
                'identity' => ['Identity verification with National ID/Fayda and verified phone is required for this transaction.'],
            ]);
        }
    }
}
