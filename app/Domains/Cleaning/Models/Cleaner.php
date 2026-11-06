<?php

declare(strict_types=1);

namespace App\Domains\Cleaning\Models;

use App\Domains\Host\Models\Host;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Cleaner extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CleaningTask::class);
    }
}
