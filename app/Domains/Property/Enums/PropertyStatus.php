<?php

declare(strict_types=1);

namespace App\Domains\Property\Enums;

enum PropertyStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case UnderOffer = 'under_offer';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Rented = 'rented';
    case Leased = 'leased';
    case Occupied = 'occupied';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
