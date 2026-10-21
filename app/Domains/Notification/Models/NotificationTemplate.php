<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationTemplate extends Model
{
    protected $fillable = [
        'event_key',
        'channels_json',
        'subject_en',
        'subject_am',
        'subject_om',
        'body_en',
        'body_am',
        'body_om',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels_json' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
