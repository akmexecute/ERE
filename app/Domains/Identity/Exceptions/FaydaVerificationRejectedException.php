<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

final class FaydaVerificationRejectedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $rejectionCode = null,
        public readonly ?string $rejectionReason = null
    ) {
        parent::__construct($message);
    }
}

