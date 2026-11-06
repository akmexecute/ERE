<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CheckIn extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'house_manual_unlocked_at' => 'datetime',
        'checked_in_at' => 'datetime',
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
