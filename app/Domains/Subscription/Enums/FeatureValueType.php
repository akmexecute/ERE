<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Enums;

enum FeatureValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Unlimited = 'unlimited';
    case Text = 'text';
    case Select = 'select';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Boolean (Enabled/Disabled)',
            self::Integer => 'Integer (Numerical Limit)',
            self::Unlimited => 'Unlimited Quota',
            self::Text => 'Text Label / Tier',
            self::Select => 'Select Option',
        };
    }
}

