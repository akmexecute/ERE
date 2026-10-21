<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

enum PhonePrivacyMode: string
{
    case ShowPhone = 'show_phone';
    case HidePhone = 'hide_phone';
    case MessagingOnly = 'messaging_only';

    public function allowsPhoneReveal(): bool
    {
        return $this === self::ShowPhone;
    }
}
