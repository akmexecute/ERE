<?php

declare(strict_types=1);

namespace App\Domains\Offer\Models;

use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferNegotiationEvent extends Model
{
    protected $table = 'offer_negotiation_events';

    public $timestamps = false;

    protected $fillable = [
        'property_offer_id',
        'round_number',
        'actor_party',
        'actor_user_id',
        'event_type',
        'proposed_amount_etb',
        'message',
        'created_at',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'proposed_amount_etb' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Immutable negotiation event: Historical turn events cannot be modified.');
        });

        static::deleting(function (): void {
            throw new DomainException('Immutable negotiation event: Historical turn events cannot be deleted.');
        });
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(PropertyOffer::class, 'property_offer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
