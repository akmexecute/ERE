<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CheckOut extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'condition_photos_json' => 'array',
        'damage_claim_required' => 'boolean',
        'is_missed_checkout_fallback' => 'boolean',
        'checked_out_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
