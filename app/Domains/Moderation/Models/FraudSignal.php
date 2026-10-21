<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Models;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FraudSignal extends Model
{
    protected $fillable = [
        'listing_id',
        'property_id',
        'signal_type',
        'severity',
        'matched_property_id',
        'matched_listing_id',
        'confidence_score',
        'evidence_json',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'evidence_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function matchedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'matched_property_id');
    }

    public function matchedListing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'matched_listing_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
