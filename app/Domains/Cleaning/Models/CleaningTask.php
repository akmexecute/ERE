<?php

declare(strict_types=1);

namespace App\Domains\Cleaning\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Host\Models\Host;
use App\Domains\Property\Models\Property;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class CleaningTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => CleaningTaskStatus::class,
        'scheduled_date' => 'date',
        'checklist_json' => 'array',
        'started_at' => 'datetime',
        'inspected_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->task_code)) {
                $model->task_code = 'CLN-'.date('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function accommodationUnit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(Cleaner::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }
}
