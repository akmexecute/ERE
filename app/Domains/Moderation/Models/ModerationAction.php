<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Models;

use App\Domains\Listing\Models\Listing;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ModerationAction extends Model
{
    public $timestamps = false;

    protected $table = 'moderation_actions';

    protected $fillable = [
        'listing_id',
        'moderator_id',
        'from_status',
        'to_status',
        'action_type',
        'reason_code',
        'feedback_to_seller',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
