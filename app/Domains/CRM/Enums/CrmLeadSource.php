<?php

declare(strict_types=1);

namespace App\Domains\CRM\Enums;

enum CrmLeadSource: string
{
    case Website = 'website';
    case Mobile = 'mobile';
    case Facebook = 'facebook';
    case Google = 'google';
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Referral = 'referral';
    case Phone = 'phone';
    case Advertisement = 'advertisement';
    case Agent = 'agent';
    case WalkIn = 'walk_in';
    case PortalReferral = 'portal_referral';
    case SocialMedia = 'social_media';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Mobile => 'Mobile App',
            self::Facebook => 'Facebook Lead',
            self::Google => 'Google Search / Ads',
            self::Telegram => 'Telegram Channel / Bot',
            self::WhatsApp => 'WhatsApp Business',
            self::Referral => 'Client Referral',
            self::Phone => 'Inbound Phone Call',
            self::Advertisement => 'Outdoor / Billboard / Print',
            self::Agent => 'Agent Network',
            self::WalkIn => 'Branch Walk-In',
            self::PortalReferral => 'Portal Referral',
            self::SocialMedia => 'Social Media (TikTok / IG / LinkedIn)',
        };
    }
}
