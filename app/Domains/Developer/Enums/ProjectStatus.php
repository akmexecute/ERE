<?php

declare(strict_types=1);

namespace App\Domains\Developer\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case PreConstruction = 'pre_construction';
    case UnderConstruction = 'under_construction';
    case NearCompletion = 'near_completion';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning & Approvals',
            self::PreConstruction => 'Pre-Construction & Site Prep',
            self::UnderConstruction => 'Under Active Construction',
            self::NearCompletion => 'Near Completion & Finishing',
            self::Completed => 'Construction Completed',
            self::Delivered => 'Delivered to Homeowners',
            self::Cancelled => 'Cancelled',
        };
    }
}
