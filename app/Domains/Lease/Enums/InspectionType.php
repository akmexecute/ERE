<?php

declare(strict_types=1);

namespace App\Domains\Lease\Enums;

enum InspectionType: string
{
    case MoveIn = 'move_in';
    case MoveOut = 'move_out';

    public function label(): string
    {
        return match ($this) {
            self::MoveIn => 'Move-In Inspection',
            self::MoveOut => 'Move-Out Inspection',
        };
    }
}
