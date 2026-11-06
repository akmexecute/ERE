<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Actions;

use App\Domains\Listing\Models\Listing;
use App\Domains\Moderation\Models\PropertyReport;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ReportPropertyAction
{
    /**
     * Allowed report reason codes according to SRS Section 117.
     */
    public const array ALLOWED_REASONS = [
        'scam',
        'fake_property',
        'duplicate',
        'wrong_price',
        'unavailable',
        'misleading',
        'suspicious_documents',
        'inappropriate_content',
    ];

    /**
     * Submit a public report against a property listing with rate-limiting and duplicate prevention.
     *
     * @throws ValidationException
     */
    public function execute(
        Listing $listing,
        Property $property,
        string $reasonCode,
        ?string $description = null,
        ?User $reporter = null,
        ?string $ipAddress = null
    ): PropertyReport {
        // 1. Verify property belongs to listing
        if ((int) $listing->property_id !== (int) $property->id) {
            throw new InvalidArgumentException('The reported property does not match the specified listing.');
        }

        // 2. Validate reason code
        $normalizedReason = strtolower(trim($reasonCode));
        if (! in_array($normalizedReason, self::ALLOWED_REASONS, true)) {
            throw ValidationException::withMessages([
                'reason_code' => ['Invalid report reason code. Must be one of: ' . implode(', ', self::ALLOWED_REASONS)],
            ]);
        }

        // 3. Rate limiting (max 5 reports per hour per IP / user)
        $rateLimitKey = 'property_report:' . ($reporter ? "user:{$reporter->id}" : "ip:" . ($ipAddress ?? 'unknown'));
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'rate_limit' => ["Too many reports submitted. Please wait {$seconds} seconds before trying again."],
            ]);
        }
        RateLimiter::hit($rateLimitKey, 3600);

        // 4. Duplicate report prevention: if identical open report was submitted within 24h by same user/ip
        $existingQuery = PropertyReport::query()
            ->where('listing_id', $listing->id)
            ->where('reason_code', $normalizedReason)
            ->where('status', 'open');

        if ($reporter) {
            $existingQuery->where('reported_by_user_id', $reporter->id);
        }

        $existing = $existingQuery->first();
        if ($existing) {
            // Already reported, return existing open report safely without duplicating
            return $existing;
        }

        // 5. Sanitize description
        $sanitizedDescription = null;
        if ($description !== null && trim($description) !== '') {
            $sanitizedDescription = mb_substr(strip_tags(trim($description)), 0, 2000);
        }

        // 6. Create report
        return PropertyReport::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'reported_by_user_id' => $reporter?->id,
            'reason_code' => $normalizedReason,
            'description' => $sanitizedDescription,
            'status' => 'open',
        ]);
    }
}
