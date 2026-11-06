<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return match ($this) {
            self::Open => match ($locale) {
                'am' => 'ክፍት',
                'om' => 'Bantamaa',
                default => 'Open',
            },
            self::Assigned => match ($locale) {
                'am' => 'ባለሙያ የተመደበለት',
                'om' => 'Ramadameera',
                default => 'Assigned',
            },
            self::InProgress => match ($locale) {
                'am' => 'በጥገና ሂደት ላይ',
                'om' => 'Adeemsarra Jira',
                default => 'In Progress',
            },
            self::Waiting => match ($locale) {
                'am' => 'እቃ / ክፍያ በመጠባበቅ ላይ',
                'om' => 'Eeggachaa Jira',
                default => 'Waiting on Parts / Approval',
            },
            self::Completed => match ($locale) {
                'am' => 'ተጠናቋል',
                'om' => 'Xumurameera',
                default => 'Completed',
            },
            self::Closed => match ($locale) {
                'am' => 'ተዘግቷል',
                'om' => 'Cufameera',
                default => 'Closed',
            },
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Open => in_array($target, [self::Assigned, self::InProgress, self::Closed], true),
            self::Assigned => in_array($target, [self::InProgress, self::Waiting, self::Completed], true),
            self::InProgress => in_array($target, [self::Waiting, self::Completed], true),
            self::Waiting => in_array($target, [self::InProgress, self::Completed], true),
            self::Completed => in_array($target, [self::InProgress, self::Closed], true),
            self::Closed => false,
        };
    }
}
