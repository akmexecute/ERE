<?php

declare(strict_types=1);

namespace App\Domains\Identity\Contracts;

interface FaydaProviderInterface
{
    /**
     * Check if the official Fayda API provider is fully configured with endpoint and credentials.
     */
    public function isConfigured(): bool;

    /**
     * Get provider identifying name.
     */
    public function getProviderName(): string;

    /**
     * Perform authoritative Fayda verification against provider.
     *
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
    public function verifyNationalId(string $nationalId, array $attributes = []): array;
}

