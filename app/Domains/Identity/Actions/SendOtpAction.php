<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Contracts\SmsGatewayInterface;
use App\Domains\Identity\Support\EthiopianPhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class SendOtpAction
{
    private SmsGatewayInterface $smsGateway;

    public function __construct(
        ?SmsGatewayInterface $smsGateway = null
    ) {
        $this->smsGateway = $smsGateway ?? app(SmsGatewayInterface::class);
    }

    public function execute(string $rawPhone, string $purpose = 'login', ?string $ipAddress = null): string
    {
        $phone = EthiopianPhoneNormalizer::normalize($rawPhone);
        $rateKeyPhone = "otp_send_phone:{$phone}";
        $rateKeyIp = "otp_send_ip:{$ipAddress}";

        // Enforce per-phone and per-IP throttling (relaxed in local for automated browser test runs)
        $maxPhoneAttempts = (int) config('services.otp.max_attempts', app()->environment('local') ? 100 : 3);
        if (RateLimiter::tooManyAttempts($rateKeyPhone, maxAttempts: $maxPhoneAttempts)) {
            $seconds = RateLimiter::availableIn($rateKeyPhone);
            throw ValidationException::withMessages([
                'phone' => ["Too many OTP requests for this phone number. Please wait {$seconds} seconds."],
            ]);
        }

        $maxIpAttempts = (int) config('services.otp.max_ip_attempts', app()->environment('local') ? 500 : 15);
        if ($ipAddress && RateLimiter::tooManyAttempts($rateKeyIp, maxAttempts: $maxIpAttempts)) {
            $seconds = RateLimiter::availableIn($rateKeyIp);
            throw ValidationException::withMessages([
                'phone' => ["Too many requests from your IP address. Please wait {$seconds} seconds."],
            ]);
        }

        RateLimiter::hit($rateKeyPhone, decaySeconds: 300);
        if ($ipAddress) {
            RateLimiter::hit($rateKeyIp, decaySeconds: 300);
        }

        // Invalidate any existing unconsumed challenge for this phone & purpose to prevent parallel replay
        DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        // Cryptographically secure 6-digit challenge
        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Secure Keyed-HMAC storage (resistant to offline rainbow-table brute force)
        $codeHash = hash_hmac('sha256', $plainCode, (string) config('app.key'));

        DB::table('otp_codes')->insert([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'requested_ip' => $ipAddress,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch SMS through configured adapter
        $this->smsGateway->sendOtp($phone, $plainCode, $purpose);

        return $plainCode;
    }
}
