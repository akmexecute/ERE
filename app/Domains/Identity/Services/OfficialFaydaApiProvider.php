<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Contracts\FaydaProviderInterface;
use App\Domains\Identity\Exceptions\FaydaServiceUnavailableException;
use App\Domains\Identity\Exceptions\FaydaVerificationRejectedException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class OfficialFaydaApiProvider implements FaydaProviderInterface
{
    public function isConfigured(): bool
    {
        $enabled = (bool) config('services.fayda.enabled', false);
        $endpoint = config('services.fayda.endpoint');
        $clientId = config('services.fayda.client_id');
        $apiKey = config('services.fayda.api_key');

        return $enabled && ! empty($endpoint) && ! empty($clientId) && ! empty($apiKey);
    }

    public function getProviderName(): string
    {
        return 'National ID Program of Ethiopia (Fayda)';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   success: bool,
     *   reference: string,
     *   verified_name: ?string,
     *   masked_id: string,
     *   timestamp: string,
     *   provider: string,
     *   raw_response?: array<string, mixed>
     * }
     */
    public function verifyNationalId(string $nationalId, array $attributes = []): array
    {
        $maskedId = self::maskId($nationalId);

        if (! $this->isConfigured()) {
            Log::info("Fayda API verification requested for [{$maskedId}], but official provider is unconfigured or disabled.");
            throw new FaydaServiceUnavailableException(
                'Official Fayda API is not connected or configured. Official API verification is currently disabled in favor of ERE manual compliance review.'
            );
        }

        $endpoint = (string) config('services.fayda.endpoint');
        $clientId = (string) config('services.fayda.client_id');
        $apiKey = (string) config('services.fayda.api_key');
        $timeout = (int) config('services.fayda.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->withHeaders([
                    'X-Client-Id' => $clientId,
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($endpoint . '/api/v1/identity/verify', [
                    'fayda_id' => $nationalId,
                    'attributes' => $attributes,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Official Fayda API successfully verified identity [{$maskedId}]. Reference: " . ($data['reference'] ?? 'N/A'));

                return [
                    'success' => true,
                    'reference' => (string) ($data['reference'] ?? 'FAYDA-' . uniqid()),
                    'verified_name' => $data['full_name'] ?? $data['name'] ?? null,
                    'masked_id' => $maskedId,
                    'timestamp' => now()->toIso8601String(),
                    'provider' => $this->getProviderName(),
                    'raw_response' => $data,
                ];
            }

            if ($response->status() === 404 || $response->status() === 422) {
                $errData = $response->json() ?? [];
                Log::warning("Official Fayda API rejected identity [{$maskedId}]. Reason: " . ($errData['message'] ?? 'Not found or invalid'));
                throw new FaydaVerificationRejectedException(
                    $errData['message'] ?? 'Identity could not be verified by the official Fayda registry.',
                    (string) ($errData['error_code'] ?? 'IDENTITY_NOT_FOUND'),
                    $errData['reason'] ?? 'Invalid National ID or identity mismatch'
                );
            }

            Log::error("Official Fayda API gateway returned HTTP {$response->status()} for [{$maskedId}].");
            throw new FaydaServiceUnavailableException(
                "Official Fayda API gateway error (HTTP {$response->status()}). Please try again later or request manual review."
            );
        } catch (FaydaVerificationRejectedException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Official Fayda API connection failure for [{$maskedId}]: {$e->getMessage()}");
            throw new FaydaServiceUnavailableException(
                'Failed to communicate with official Fayda API service: ' . $e->getMessage()
            );
        }
    }

    public static function maskId(string $id): string
    {
        $clean = trim($id);
        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return 'FAN-****-' . substr($clean, -4);
    }
}

