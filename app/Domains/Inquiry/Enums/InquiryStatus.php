<?php

declare(strict_types=1);

namespace App\Domains\Inquiry\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Viewing = 'viewing';
    case Negotiating = 'negotiating';
    case Offer = 'offer';
    case Closed = 'closed';
    case Lost = 'lost';
}
