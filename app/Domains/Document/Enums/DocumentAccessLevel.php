<?php

declare(strict_types=1);

namespace App\Domains\Document\Enums;

enum DocumentAccessLevel: string
{
    case Public = 'public';
    case Private = 'private';
    case VerificationOnly = 'verification_only';
    case AdminOnly = 'admin_only';
    case TransactionParticipants = 'transaction_participants';
}
