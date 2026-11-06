<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

interface SmsGatewayInterface
{
    /**
     * Dispatch an OTP SMS message to an Ethiopian mobile number.
     *
     * @param string $phone E.164 format (e.g. +251911234567)
     * @param string $code Plain verification code (never logged in production)
     * @param string $purpose e.g. login, registration, phone_change
     */
    public function sendOtp(string $phone, string $code, string $purpose = 'login'): bool;
}
