<?php

declare(strict_types=1);

namespace App\Domains\Stay\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;

final readonly class AuthorizeHouseManualAccessAction
{
    /**
     * Determine if a user is authorized to view protected house manual access details.
     * SRS Section 58 & 61: Unlocked only for host or confirmed guests.
     */
    public function canAccess(StayListingProfile $profile, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // 1. Staff access
        if ($user->hasAnyRole(['super_admin', 'platform_admin'])) {
            return true;
        }

        // 2. Listing host / property owner
        $profile->loadMissing(['host', 'property']);
        if ($profile->host && $profile->host->user_id === $user->id) {
            return true;
        }
        if ($profile->property && $profile->property->owner_id === $user->id) {
            return true;
        }

        // 3. Guest with confirmed/active booking within valid window
        return Booking::query()
            ->where('stay_listing_profile_id', $profile->id)
            ->where('guest_user_id', $user->id)
            ->whereIn('status', [
                BookingStatus::Confirmed,
                BookingStatus::CheckedIn,
                BookingStatus::Active,
            ])
            ->where('check_out_date', '>=', now()->toDateString())
            ->exists();
    }
}
