<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationPreference extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'channel_in_app',
        'channel_email',
        'channel_sms',
        'channel_push',
        'channel_telegram',
        'marketing_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'channel_in_app' => 'boolean',
            'channel_email' => 'boolean',
            'channel_sms' => 'boolean',
            'channel_push' => 'boolean',
            'channel_telegram' => 'boolean',
            'marketing_opt_in' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
