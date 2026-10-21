<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Support\EthiopianPhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class SendOtpAction
{
    public function execute(string $rawPhone, string $purpose = 'login', ?string $ipAddress = null): string
    {
        $phone = EthiopianPhoneNormalizer::normalize($rawPhone);
        $rateKey = "otp_send:{$phone}:{$ipAddress}";

        if (RateLimiter::tooManyAttempts($rateKey, maxAttempts: 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'phone' => ["Too many OTP requests. Please wait {$seconds} seconds before trying again."],
            ]);
        }

        RateLimiter::hit($rateKey, decaySeconds: 600);

        $plainCode = (string) random_int(100000, 999999);

        DB::table('otp_codes')->insert([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => hash('sha256', $plainCode),
            'attempts' => 0,
            'requested_ip' => $ipAddress,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // In production, dispatch SendSmsOtpJob($phone, $plainCode) to Ethio Telecom / AfroMessage / GeezSMS gateway
        return $plainCode;
    }
}
