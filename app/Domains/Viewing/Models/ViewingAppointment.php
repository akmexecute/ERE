<?php

declare(strict_types=1);

namespace App\Domains\Viewing\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ViewingAppointment extends Model
{
    protected $table = 'viewing_appointments';

    protected $fillable = [
        'uuid',
        'appointment_code',
        'listing_id',
        'property_id',
        'buyer_user_id',
        'client_id',
        'seller_or_agent_user_id',
        'agent_id',
        'crm_lead_id',
        'scheduled_date',
        'scheduled_time_slot',
        'slot_start_time',
        'slot_end_time',
        'scheduled_at',
        'viewing_type',
        'status',
        'visitor_notes',
        'agent_confirmation_notes',
        'confirmed_at',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function parseTimeSlot(string $slot): array
    {
        $parts = explode('-', $slot);
        $startRaw = isset($parts[0]) ? trim($parts[0]) : '09:00';
        $endRaw = isset($parts[1]) ? trim($parts[1]) : '10:00';

        $startTimestamp = strtotime($startRaw);
        $endTimestamp = strtotime($endRaw);

        $start = $startTimestamp !== false ? date('H:i', $startTimestamp) : '09:00';
        $end = $endTimestamp !== false ? date('H:i', $endTimestamp) : '10:00';

        return [$start, $end];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->appointment_code)) {
                $model->appointment_code = 'VWG-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function (self $model): void {
            if (! empty($model->scheduled_time_slot)) {
                [$start, $end] = static::parseTimeSlot($model->scheduled_time_slot);
                $model->slot_start_time = $start;
                $model->slot_end_time = $end;
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_or_agent_user_id');
    }
}
