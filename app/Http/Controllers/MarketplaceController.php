<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use App\Domains\Marketplace\Actions\ParseNaturalPropertyQueryAction;
use App\Domains\Marketplace\Queries\SearchMarketplaceListingsQuery;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Support\Money\EtbFormatter;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MarketplaceController extends Controller
{
    public function search(
        Request $request,
        ParseNaturalPropertyQueryAction $naturalParser,
        SearchMarketplaceListingsQuery $searchQuery
    ): View {
        $rawQuery = trim((string) $request->input('q', ''));
        $parsed = $rawQuery !== '' ? $naturalParser->execute($rawQuery) : [];

        $categoryParam = $request->input('category');
        $categoryId = $request->input('category_id');
        $categoryCode = $request->input('category_code', $parsed['category_code'] ?? null);

        if ($categoryParam) {
            $cat = PropertyCategory::query()
                ->where('slug', strtolower((string) $categoryParam))
                ->orWhere('code', strtoupper((string) $categoryParam))
                ->first();
            if ($cat) {
                $categoryId = $cat->id;
            }
        }

        $rawMode = $request->input('transaction_mode') ?? $request->input('mode') ?? ($parsed['transaction_mode'] ?? null);
        $transactionMode = $rawMode ? strtoupper((string) $rawMode) : ($categoryParam ? null : 'BUY');

        $filters = [
            'transaction_mode' => $transactionMode,
            'category_id' => $categoryId,
            'category_code' => $categoryCode,
            'property_type_id' => $request->input('property_type_id'),
            'amenities' => $request->input('amenities', ! empty($parsed['amenities']) ? $parsed['amenities'] : null),
            'sort' => $request->input('sort', 'relevance'),
            'min_price_etb' => $request->input('min_price', $parsed['min_price_etb'] ?? null),
            'max_price_etb' => $request->input('max_price', $parsed['max_price_etb'] ?? null),
            'min_bedrooms' => $request->input('bedrooms', $parsed['min_bedrooms'] ?? null),
            'min_verification_level' => $request->boolean('verified_only') ? 1 : ($request->filled('min_verification_level') ? (int) $request->input('min_verification_level') : null),
            'ne_lat' => $request->input('ne_lat'),
            'ne_lng' => $request->input('ne_lng'),
            'sw_lat' => $request->input('sw_lat'),
            'sw_lng' => $request->input('sw_lng'),
            'center_lat' => $request->input('center_lat'),
            'center_lng' => $request->input('center_lng'),
            'radius_meters' => $request->input('radius_meters'),
        ];

        // Match location keyword against dynamic location hierarchy
        $locationKeyword = $request->input('location', $parsed['location_keyword'] ?? null);
        if ($locationKeyword) {
            $matchedLoc = Location::query()
                ->where('slug', $locationKeyword)
                ->orWhere('name_en', 'like', "%{$locationKeyword}%")
                ->orWhere('name_am', 'like', "%{$locationKeyword}%")
                ->orWhere('name_om', 'like', "%{$locationKeyword}%")
                ->first();

            if ($matchedLoc) {
                if ($matchedLoc->level === 'city') {
                    $filters['city_id'] = $matchedLoc->id;
                } elseif ($matchedLoc->level === 'subcity') {
                    $filters['subcity_id'] = $matchedLoc->id;
                } elseif ($matchedLoc->level === 'region') {
                    $filters['region_id'] = $matchedLoc->id;
                } elseif ($matchedLoc->level === 'woreda') {
                    $filters['woreda_id'] = $matchedLoc->id;
                } elseif ($matchedLoc->level === 'kebele') {
                    $filters['kebele_id'] = $matchedLoc->id;
                } else {
                    $filters['neighborhood_id'] = $matchedLoc->id;
                }
            }
        }

        // Match property type against configurable taxonomy
        $typeSlug = $request->input('property_type', $parsed['property_type_slug'] ?? null);
        if ($typeSlug) {
            $matchedType = PropertyType::query()->where('slug', $typeSlug)->first();
            if ($matchedType) {
                $filters['property_type_id'] = $matchedType->id;
            }
        }

        $paginator = $searchQuery->execute($filters);

        $listingsData = $paginator->getCollection()->map(function (Listing $listing): array {
            $prop = $listing->property;
            $area = (float) ($prop->land_area_sqm ?: $prop->building_area_sqm ?: 0);
            $cover = $prop->images->firstWhere('is_cover', true) ?? $prop->images->first();

            $locationName = $prop->subcity?->name_en
                ? "{$prop->subcity->name_en}, " . ($prop->city?->name_en ?? 'Addis Ababa')
                : ($prop->city?->name_en ?? 'Addis Ababa, Ethiopia');

            return [
                'id' => $listing->id,
                'code' => $prop->property_code,
                'title' => $prop->title_en,
                'subcity' => $locationName,
                'price_formatted' => EtbFormatter::format($listing->price_etb),
                'compact_price' => EtbFormatter::compact($listing->price_etb),
                'price_per_sqm' => $listing->price_per_sqm_etb ? EtbFormatter::format($listing->price_per_sqm_etb) . ' / m²' : '—',
                'bedrooms' => (int) ($prop->bedrooms ?? 0),
                'bathrooms' => (int) ($prop->bathrooms ?? 0),
                'area_sqm' => (int) $area,
                'verification_level' => (int) ($prop->verification_level ?? 0),
                'lat' => $prop->latitude ? (float) $prop->latitude : 9.0192,
                'lng' => $prop->longitude ? (float) $prop->longitude : 38.7525,
                'url' => url("/listings/{$listing->slug}"),
                'image_url' => $cover ? asset('storage/' . $cover->original_sanitized_path) : null,
            ];
        })->values()->all();

        return view('marketplace.search-split-view', [
            'paginator' => $paginator,
            'listingsJson' => $listingsData,
            'filters' => $filters,
            'rawQuery' => $rawQuery,
        ]);
    }

    public function show(string $slug): View
    {
        $listing = Listing::query()
            ->where('slug', $slug)
            ->where('status', ListingStatus::Published->value)
            ->with(['property.images', 'property.owner', 'property.amenities', 'property.category', 'property.propertyType', 'property.city', 'property.subcity'])
            ->firstOrFail();

        $listing->increment('views_count');

        return view('marketplace.property-detail', [
            'listing' => $listing,
            'property' => $listing->property,
        ]);
    }
}
