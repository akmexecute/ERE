<?php

declare(strict_types=1);

namespace App\Domains\Verification\Models;

use App\Domains\User\Models\User;
use App\Domains\Verification\Enums\VerificationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerificationReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'verification_id',
        'officer_id',
        'from_status',
        'to_status',
        'granted_level',
        'decision_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_level' => VerificationLevel::class,
            'created_at' => 'datetime',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class, 'verification_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
