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
    /**
     * Strictly permit only non-privileged, self-service customer roles on registration.
     */
    public const array ALLOWED_SELF_SERVICE_ROLES = [
        'buyer',
        'tenant',
        'property_owner',
        'host',
        'agent',
    ];

    public function execute(
        string $rawPhone,
        string $code,
        string $purpose = 'login',
        ?string $name = null,
        string $initialRole = 'buyer',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $phone = EthiopianPhoneNormalizer::normalize($rawPhone);
        $trimmedCode = trim($code);

        // Sanitize initial role: Never permit public privilege escalation into staff/admin roles
        $safeRole = in_array($initialRole, self::ALLOWED_SELF_SERVICE_ROLES, true)
            ? $initialRole
            : 'buyer';

        /** @var object|null $otpRecord */
        $otpRecord = DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        // Validate existence, expiration, and attempt limits
        if (! $otpRecord || now()->greaterThan($otpRecord->expires_at) || $otpRecord->attempts >= 5) {
            $isBruteForceExhausted = (bool) ($otpRecord && $otpRecord->attempts >= 5);
            $this->logAttempt(null, $phone, false, $ipAddress, $userAgent, forceSuspicious: $isBruteForceExhausted);
            throw ValidationException::withMessages([
                'code' => ['The verification code has expired or exceeded maximum allowed attempts.'],
            ]);
        }

        // Verify Keyed-HMAC constant-time match
        $computedHash = hash_hmac('sha256', $trimmedCode, (string) config('app.key'));
        if (! hash_equals($otpRecord->code_hash, $computedHash)) {
            DB::table('otp_codes')->where('id', $otpRecord->id)->increment('attempts');
            $this->logAttempt(null, $phone, false, $ipAddress, $userAgent);
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code. Please check and try again.'],
            ]);
        }

        return DB::transaction(function () use ($otpRecord, $phone, $name, $safeRole, $ipAddress, $userAgent): User {
            $lockedOtp = DB::table('otp_codes')
                ->where('id', $otpRecord->id)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->first();

            if (! $lockedOtp) {
                throw ValidationException::withMessages([
                    'code' => ['The verification code has already been consumed.'],
                ]);
            }

            // Atomically consume challenge
            DB::table('otp_codes')->where('id', $lockedOtp->id)->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

            // Resolve or create user account
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

            // Guard against suspended or banned accounts
            if ($user->status !== 'active') {
                $this->logAttempt($user->id, $phone, false, $ipAddress, $userAgent, forceSuspicious: true);
                throw ValidationException::withMessages([
                    'phone' => ['Your account is currently suspended or inactive. Please contact support.'],
                ]);
            }

            if ($user->phone_verified_at === null) {
                $user->forceFill([
                    'phone_verified_at' => now(),
                    'verification_level' => max(1, (int) $user->verification_level),
                ])->save();
            }

            // Assign safe self-service role
            $user->assignRoleBySlug($safeRole);

            $this->logAttempt($user->id, $phone, true, $ipAddress, $userAgent);

            return $user;
        });
    }

    private function logAttempt(?int $userId, string $phone, bool $succeeded, ?string $ip, ?string $ua, bool $forceSuspicious = false): void
    {
        $isSuspicious = $forceSuspicious;

        if (! $isSuspicious && $userId !== null) {
            $userStatus = DB::table('users')->where('id', $userId)->value('status');
            if ($userStatus === 'suspended' || $userStatus === 'banned') {
                $isSuspicious = true;
            }
        }

        if (! $isSuspicious && ! $succeeded) {
            // Rule: >= 3 recent failed attempts within 15 minutes indicates suspicious brute force / probing
            $window = now()->subMinutes(15);
            $recentFailures = DB::table('login_histories')
                ->where('created_at', '>=', $window)
                ->where('succeeded', false)
                ->where(function ($query) use ($ip, $phone): void {
                    if ($ip) {
                        $query->where('ip_address', $ip);
                    }
                    $query->orWhere('phone_or_email', $phone);
                })
                ->count();

            if ($recentFailures >= 3) {
                $isSuspicious = true;
            }
        }

        DB::table('login_histories')->insert([
            'user_id' => $userId,
            'phone_or_email' => $phone,
            'auth_method' => 'otp',
            'succeeded' => $succeeded,
            'is_suspicious' => $isSuspicious,
            'ip_address' => $ip,
            'user_agent' => $ua ? mb_substr($ua, 0, 512) : null,
            'created_at' => now(),
        ]);
    }
}
