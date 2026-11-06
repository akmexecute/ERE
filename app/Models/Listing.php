<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Listing\Models\Listing as DomainListing;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Listing extends DomainListing
{
    use HasFactory;

    public function getPriceAttribute(): ?float
    {
        return $this->price_etb ? (float) $this->price_etb : null;
    }

    public function setPriceAttribute(float|int|string $value): void
    {
        $this->attributes['price_etb'] = $value;
    }
}
