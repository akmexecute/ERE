<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Analytics\Models\MarketAnalyticsSnapshot;
use App\Domains\Analytics\Services\MarketAnalyticsCalculator;
use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use App\Domains\Moderation\Models\FraudSignal;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\Verification\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $user = $request->user();

        // Enforce admin authorization
        if (! $user->hasAnyRole(['super_admin', 'platform_admin', 'admin', 'moderator', 'verification_officer'])) {
            throw new AccessDeniedHttpException('Access restricted to national platform administrators.');
        }

        // Live database-backed KPIs (SRS Section 120 & 214)
        $totalUsers = User::query()->count();
        $masterProperties = Property::query()->count();
        $activeListings = Listing::query()->where('status', ListingStatus::Published)->count();
        $inquiryVolume = Inquiry::query()->count();
        $pendingVerifications = Verification::query()->where('status', 'pending')->count();
        $openFraudSignals = FraudSignal::query()->where('status', 'open')->count();

        // City Analytics Comparison (SRS Section 215)
        $cities = Location::query()->where('level', 'city')->get();
        $cityAnalytics = [];

        foreach ($cities as $city) {
            $activeSaleCount = Listing::query()
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.status', ListingStatus::Published)
                ->where('listings.transaction_mode', 'BUY')
                ->where('properties.city_id', $city->id)
                ->count();

            $activeRentCount = Listing::query()
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.status', ListingStatus::Published)
                ->whereIn('listings.transaction_mode', ['RENT', 'LEASE'])
                ->where('properties.city_id', $city->id)
                ->count();

            $cityPrices = Listing::query()
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.status', ListingStatus::Published)
                ->where('listings.transaction_mode', 'BUY')
                ->where('properties.city_id', $city->id)
                ->pluck('listings.price_etb')
                ->map(fn ($p) => (float) $p)
                ->sort()
                ->values()
                ->all();

            $medianPrice = null;
            if ($cityPrices !== []) {
                $count = count($cityPrices);
                $mid = intdiv($count, 2);
                $medianPrice = ($count % 2 === 0)
                    ? round(($cityPrices[$mid - 1] + $cityPrices[$mid]) / 2, 2)
                    : round($cityPrices[$mid], 2);
            }

            $avgSqmPrice = Listing::query()
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.status', ListingStatus::Published)
                ->where('properties.city_id', $city->id)
                ->whereNotNull('listings.price_per_sqm_etb')
                ->where('listings.price_per_sqm_etb', '>', 0)
                ->avg('listings.price_per_sqm_etb');

            $inquiriesCount = Inquiry::query()
                ->join('properties', 'properties.id', '=', 'inquiries.property_id')
                ->where('properties.city_id', $city->id)
                ->count();

            $cityAnalytics[] = [
                'city_name' => $city->name_en.($city->name_am ? ' ('.$city->name_am.')' : ''),
                'name_en' => $city->name_en,
                'name_am' => $city->name_am,
                'active_sales' => $activeSaleCount,
                'active_sale_count' => $activeSaleCount,
                'active_rentals' => $activeRentCount,
                'active_rent_count' => $activeRentCount,
                'median_price' => $medianPrice,
                'median_sale_price' => $medianPrice,
                'avg_price_per_sqm' => $avgSqmPrice ? round((float) $avgSqmPrice, 2) : null,
                'inquiries_count' => $inquiriesCount,
            ];
        }

        $data = [
            'kpis' => [
                'total_users' => $totalUsers,
                'master_properties' => $masterProperties,
                'active_listings' => $activeListings,
                'inquiry_volume' => $inquiryVolume,
                'pending_verifications' => $pendingVerifications,
                'open_fraud_signals' => $openFraudSignals,
            ],
            'cityAnalytics' => $cityAnalytics,
            'disclosureNotice' => MarketAnalyticsCalculator::disclosureNotice(),
            'dataBasis' => MarketAnalyticsCalculator::DATA_BASIS_ASKING_PRICE,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('admin.dashboard', $data);
    }
}
