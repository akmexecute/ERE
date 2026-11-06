<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EthiopianSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $senderId = null,
        private ?string $endpoint = null
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.sms.api_key', '');
        $this->senderId = $senderId ?? (string) config('services.sms.sender_id', 'EthioProp');
        $this->endpoint = $endpoint ?? (string) config('services.sms.endpoint', 'https://api.afromessage.com/api/send');
    }

    public function sendOtp(string $phone, string $code, string $purpose = 'login'): bool
    {
        // Guard if credentials are not configured
        if (empty($this->apiKey)) {
            Log::info("SMS Gateway: Simulated OTP send to {$phone} (purpose: {$purpose})");
            return true;
        }

        try {
            $message = "Your EthioProperty verification code is: {$code}. Valid for 5 minutes. Do not share this code.";

            $response = Http::timeout(5)
                ->withToken($this->apiKey)
                ->post($this->endpoint, [
                    'to' => $phone,
                    'from' => $this->senderId,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::error('SMS Gateway Error: HTTP ' . $response->status() . ' - ' . $response->body());
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('SMS Gateway Connection Exception: ' . $e->getMessage());
            return false;
        }
    }
}
