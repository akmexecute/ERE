<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Support\EthiopianPhoneNormalizer;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class VerifyOtpAndAuthenticateAction
{
    public function execute(
        string $rawPhone,
        string $code,
        ?string $name = null,
        string $initialRole = 'buyer',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $phone = EthiopianPhoneNormalizer::normalize($rawPhone);

        $otpRecord = DB::table('otp_codes')
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $otpRecord || $otpRecord->attempts >= 5) {
            $this->logAttempt(null, $phone, false, $ipAddress, $userAgent);
            throw ValidationException::withMessages([
                'code' => ['The verification code has expired or exceeded maximum attempts.'],
            ]);
        }

        if (! hash_equals($otpRecord->code_hash, hash('sha256', trim($code)))) {
            DB::table('otp_codes')->where('id', $otpRecord->id)->increment('attempts');
            $this->logAttempt(null, $phone, false, $ipAddress, $userAgent);
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please check and try again.'],
            ]);
        }

        return DB::transaction(function () use ($otpRecord, $phone, $name, $initialRole, $ipAddress, $userAgent): User {
            DB::table('otp_codes')->where('id', $otpRecord->id)->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

            $user = User::query()->firstOrCreate(
                ['phone' => $phone],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $name ?: 'User '.substr($phone, -4),
                    'phone_verified_at' => now(),
                    'verification_level' => 1, // Level 1: Phone/Identity OTP Verified
                    'status' => 'active',
                ]
            );

            if ($user->phone_verified_at === null) {
                $user->forceFill([
                    'phone_verified_at' => now(),
                    'verification_level' => max(1, (int) $user->verification_level),
                ])->save();
            }

            $user->assignRoleBySlug($initialRole);
            $this->logAttempt($user->id, $phone, true, $ipAddress, $userAgent);

            return $user;
        });
    }

    private function logAttempt(?int $userId, string $phone, bool $succeeded, ?string $ip, ?string $ua): void
    {
        DB::table('login_histories')->insert([
            'user_id' => $userId,
            'phone_or_email' => $phone,
            'auth_method' => 'otp',
            'succeeded' => $succeeded,
            'is_suspicious' => false,
            'ip_address' => $ip,
            'user_agent' => $ua ? mb_substr($ua, 0, 512) : null,
            'created_at' => now(),
        ]);
    }
}
