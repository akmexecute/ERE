<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsGatewayInterface;

final class ArraySmsGateway implements SmsGatewayInterface
{
    /**
     * @var array<int, array{phone: string, code: string, purpose: string, sent_at: string}>
     */
    private static array $sentMessages = [];

    public function sendOtp(string $phone, string $code, string $purpose = 'login'): bool
    {
        self::$sentMessages[] = [
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'sent_at' => now()->toIso8601String(),
        ];

        return true;
    }

    /**
     * @return array<int, array{phone: string, code: string, purpose: string, sent_at: string}>
     */
    public static function getSentMessages(): array
    {
        return self::$sentMessages;
    }

    public static function reset(): void
    {
        self::$sentMessages = [];
    }

    public static function getLastCodeFor(string $phone): ?string
    {
        for ($i = count(self::$sentMessages) - 1; $i >= 0; $i--) {
            if (self::$sentMessages[$i]['phone'] === $phone) {
                return self::$sentMessages[$i]['code'];
            }
        }

        return null;
    }
}
