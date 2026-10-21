<?php

declare(strict_types=1);

namespace App\Domains\Verification\Models;

use App\Domains\User\Models\User;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Verification extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'verifiable_type',
        'verifiable_id',
        'requested_by_user_id',
        'requested_level',
        'approved_level',
        'badge_code',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'internal_officer_notes',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_level' => 'integer',
            'approved_level' => VerificationLevel::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class, 'verification_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VerificationReview::class, 'verification_id')->orderByDesc('created_at');
    }
}
