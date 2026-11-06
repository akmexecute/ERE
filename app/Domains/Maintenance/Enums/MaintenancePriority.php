<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Enums;

enum MaintenancePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Emergency = 'emergency';

    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return match ($this) {
            self::Low => match ($locale) {
                'am' => 'ዝቅተኛ ቅድሚያ',
                'om' => 'Dursa Gad-aanaa',
                default => 'Low',
            },
            self::Normal => match ($locale) {
                'am' => 'መደበኛ',
                'om' => 'Idilee',
                default => 'Normal',
            },
            self::High => match ($locale) {
                'am' => 'ከፍተኛ ቅድሚያ',
                'om' => 'Dursa Ol-aanaa',
                default => 'High',
            },
            self::Emergency => match ($locale) {
                'am' => 'አስቸኳይ / አደገኛ',
                'om' => 'Ariifachiisaa',
                default => 'Emergency',
            },
        };
    }
}
