<?php

declare(strict_types=1);

namespace App\Domains\CRM\Enums;

enum CrmPipelineStage: string
{
    case NewLead = 'new_lead';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Viewing = 'viewing';
    case Negotiation = 'negotiation';
    case Offer = 'offer';
    case Closed = 'closed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New Lead',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Viewing => 'Viewing',
            self::Negotiation => 'Negotiation',
            self::Offer => 'Offer',
            self::Closed => 'Closed',
            self::Lost => 'Lost',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($next === self::Lost) {
            return $this !== self::Closed;
        }

        return match ($this) {
            self::NewLead => in_array($next, [self::Contacted, self::Qualified, self::Viewing], true),
            self::Contacted => in_array($next, [self::Qualified, self::Viewing, self::Negotiation], true),
            self::Qualified => in_array($next, [self::Viewing, self::Negotiation, self::Offer], true),
            self::Viewing => in_array($next, [self::Negotiation, self::Offer, self::Closed], true),
            self::Negotiation => in_array($next, [self::Offer, self::Closed], true),
            self::Offer => in_array($next, [self::Negotiation, self::Closed], true),
            self::Closed, self::Lost => false,
        };
    }
}
