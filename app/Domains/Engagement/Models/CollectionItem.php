<?php

declare(strict_types=1);

namespace App\Domains\Engagement\Models;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CollectionItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'collection_id',
        'listing_id',
        'property_id',
        'personal_note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(SavedCollection::class, 'collection_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
