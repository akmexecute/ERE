<?php

declare(strict_types=1);

namespace App\Domains\Offer\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegotiationOffer extends Model
{
    protected $table = 'negotiation_offers';

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'agent_id',
        'round_number',
        'offered_amount',
        'offered_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'offered_amount' => 'decimal:2',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
