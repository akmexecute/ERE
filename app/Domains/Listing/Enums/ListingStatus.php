<?php

declare(strict_types=1);

namespace App\Domains\Listing\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Published = 'published';
    case Paused = 'paused';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
