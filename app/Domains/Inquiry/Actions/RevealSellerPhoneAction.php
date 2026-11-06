<?php

declare(strict_types=1);

namespace App\Domains\Inquiry\Actions;

use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Domains\Listing\Models\Listing;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class RevealSellerPhoneAction
{
    public static function resolveEffectivePrivacy(?string $listingOverride, ?string $sellerDefault): PhonePrivacyMode
    {
        if ($listingOverride !== null && $listingOverride !== '') {
            return PhonePrivacyMode::tryFrom($listingOverride) ?? PhonePrivacyMode::HidePhone;
        }

        if ($sellerDefault !== null && $sellerDefault !== '') {
            return PhonePrivacyMode::tryFrom($sellerDefault) ?? PhonePrivacyMode::HidePhone;
        }

        return PhonePrivacyMode::HidePhone;
    }

    public function execute(
        Listing $listing,
        User $seller,
        ?User $viewer = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): string {
        $mode = self::resolveEffectivePrivacy(
            $listing->phone_privacy_override,
            $seller->phone_privacy?->value ?? (string) $seller->getRawOriginal('phone_privacy')
        );

        if (! $mode->allowsPhoneReveal()) {
            throw new AccessDeniedHttpException(
                'This seller accepts inquiries via platform messaging only and has hidden direct phone display.'
            );
        }

        // Validate relationship: seller must be publisher or property owner
        $isSeller = ($listing->published_by_user_id === $seller->id)
            || ($listing->property && $listing->property->owner_id === $seller->id);

        if (! $isSeller) {
            throw new AccessDeniedHttpException('Seller is not associated with this listing.');
        }

        $rateKey = 'phone_reveal:'.($viewer?->id ?? $ipAddress ?? 'anon');
        if (RateLimiter::tooManyAttempts($rateKey, maxAttempts: 15)) {
            throw ValidationException::withMessages([
                'phone' => ['Rate limit reached for phone number reveals. Please use platform messaging.'],
            ]);
        }
        RateLimiter::hit($rateKey, decaySeconds: 3600);

        DB::table('phone_reveal_logs')->insert([
            'listing_id' => $listing->id,
            'property_id' => $listing->property_id,
            'seller_id' => $seller->id,
            'viewer_user_id' => $viewer?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'created_at' => now(),
        ]);

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $viewer?->id,
                'action' => 'listing.phone_revealed',
                'auditable_type' => Listing::class,
                'auditable_id' => (string) $listing->id,
                'new_values' => json_encode(['seller_id' => $seller->id, 'property_id' => $listing->property_id]),
                'reason' => 'Authorized seller contact reveal',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
                'created_at' => now(),
            ]);
        }

        return (string) $seller->phone;
    }
}
