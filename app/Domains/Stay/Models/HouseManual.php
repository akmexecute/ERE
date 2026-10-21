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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }
}
