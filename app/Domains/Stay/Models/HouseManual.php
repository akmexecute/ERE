<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HouseManual extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'wifi_ssid',
        'wifi_password_encrypted',
        'check_in_method',
        'access_instructions_en',
        'access_instructions_am',
        'appliance_and_ac_guide_en',
        'parking_instructions_en',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'wifi_password_encrypted' => 'encrypted',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }

    public function getDecryptedWifiPassword(): ?string
    {
        $val = $this->wifi_password_encrypted;
        if ($val === null || $val === '') {
            return null;
        }

        try {
            return decrypt($val);
        } catch (\Throwable) {
            return (string) $val;
        }
    }
}
