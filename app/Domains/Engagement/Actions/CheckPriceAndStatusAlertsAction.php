<?php

declare(strict_types=1);

namespace App\Domains\Engagement\Actions;

use App\Domains\Engagement\Models\PriceAlert;
use App\Domains\Listing\Models\Listing;
use App\Domains\Notification\Services\MultiChannelNotificationService;
use App\Support\Money\EtbFormatter;
use Illuminate\Support\Facades\Log;

final readonly class CheckPriceAndStatusAlertsAction
{
    public function __construct(
        private MultiChannelNotificationService $notificationService = new MultiChannelNotificationService()
    ) {}

    /**
     * Evaluate price drops on a listing and dispatch alerts to subscribed buyers.
     */
    public function onPriceChanged(Listing $listing, float $oldPrice, float $newPrice): int
    {
        if ($newPrice >= $oldPrice) {
            return 0;
        }

        $alerts = PriceAlert::query()
            ->where('is_active', true)
            ->where('notify_on_price_drop', true)
            ->where(function ($q) use ($listing) {
                $q->where('listing_id', $listing->id);
                if ($listing->property_id) {
                    $q->orWhere('property_id', $listing->property_id);
                }
            })
            ->with(['user', 'property'])
            ->get();

        $dispatched = 0;
        $dropAmount = $oldPrice - $newPrice;
        $dropPercent = $oldPrice > 0 ? round(($dropAmount / $oldPrice) * 100, 1) : 0.0;
        $propertyTitle = $listing->property?->title_en ?? $listing->property?->title_am ?? "Listing #{$listing->id}";

        foreach ($alerts as $alert) {
            $user = $alert->user;
            if (! $user) {
                continue;
            }

            // Exclude listing owner from buyer alerts
            if ($user->id === $listing->published_by_user_id) {
                continue;
            }

            $this->notificationService->send(
                recipient: $user,
                eventKey: 'price.dropped',
                data: [
                    'property_title' => $propertyTitle,
                    'old_price' => EtbFormatter::format($oldPrice),
                    'new_price' => EtbFormatter::format($newPrice),
                    'drop_amount' => EtbFormatter::format($dropAmount),
                    'drop_percent' => (string) $dropPercent,
                ],
                isMarketing: false
            );

            $alert->update([
                'baseline_price_etb' => $newPrice,
                'last_triggered_at' => now(),
            ]);

            $dispatched++;
            Log::info("[PriceDropAlert] Sent to User #{$user->id} for listing #{$listing->id}: old {$oldPrice} -> new {$newPrice}");
        }

        return $dispatched;
    }

    /**
     * Evaluate listing status changes and dispatch alerts to subscribed buyers.
     */
    public function onStatusChanged(Listing $listing, string $oldStatus, string $newStatus): int
    {
        if ($oldStatus === $newStatus) {
            return 0;
        }

        $alerts = PriceAlert::query()
            ->where('is_active', true)
            ->where('notify_on_status_change', true)
            ->where(function ($q) use ($listing) {
                $q->where('listing_id', $listing->id);
                if ($listing->property_id) {
                    $q->orWhere('property_id', $listing->property_id);
                }
            })
            ->with(['user', 'property'])
            ->get();

        $dispatched = 0;
        $listingCode = $listing->slug ?? "LST-{$listing->id}";

        foreach ($alerts as $alert) {
            $user = $alert->user;
            if (! $user) {
                continue;
            }

            if ($user->id === $listing->published_by_user_id) {
                continue;
            }

            $this->notificationService->send(
                recipient: $user,
                eventKey: 'listing.status_changed',
                data: [
                    'listing_code' => $listingCode,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
                isMarketing: false
            );

            $alert->update([
                'last_triggered_at' => now(),
            ]);

            $dispatched++;
            Log::info("[StatusChangeAlert] Sent to User #{$user->id} for listing #{$listing->id}: {$oldStatus} -> {$newStatus}");
        }

        return $dispatched;
    }
}
