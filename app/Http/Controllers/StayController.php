<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Booking\Actions\CalculateCancellationRefundAction;
use App\Domains\Booking\Actions\CancelBookingAction;
use App\Domains\Booking\Actions\ConfirmBookingAction;
use App\Domains\Booking\Actions\CreateBookingHoldAction;
use App\Domains\Booking\Models\Booking;
use App\Domains\Calendar\Actions\GetStayCalendarAvailabilityAction;
use App\Domains\Calendar\Actions\ResolveDynamicStayQuoteAction;
use App\Domains\Calendar\Services\IcalSyncService;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Actions\AuthorizeHouseManualAccessAction;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\User\Models\User;
use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final class StayController extends Controller
{
    /**
     * Default profile data for the showcase stay listing.
     *
     * @return array<string, mixed>
     */
    private function getShowcaseProfile(): array
    {
        return [
            'id' => 1,
            'slug' => 'modern-garden-villa-bole',
            'property_code' => 'ETH-AA-BOLE-RES-00001245',
            'title_en' => 'Modern Garden Villa with Backup Generator — Bole Medhanialem',
            'title_am' => 'ዘመናዊ የቪላ መኖሪያ ከጀነሬተር ጋር — ቦሌ መድኃኔዓለም',
            'accommodation_model' => 'entire_place',
            'property_type' => 'Serviced Villa',
            'neighborhood' => 'Bole Medhanialem, Addis Ababa',
            'rating' => 4.92,
            'reviews_count' => 38,
            'is_superhost' => true,
            'verification_level' => 3,

            // Pricing Tokens in ETB
            'base_nightly_rate_etb' => 8500.0,
            'weekend_nightly_rate_etb' => 9500.0,
            'weekly_discount_pct' => 10.0,
            'monthly_discount_pct' => 20.0,
            'cleaning_fee_etb' => 2000.0,
            'security_deposit_etb' => 5000.0,
            'guest_service_fee_pct' => 5.618,
            'tourism_tax_pct' => 15.0,

            // Stay Limits & Times
            'min_stay_nights' => 2,
            'max_stay_nights' => 90,
            'check_in_after_time' => '14:00',
            'check_out_before_time' => '11:00',
            'max_guests' => 6,
            'max_adults' => 4,
            'max_children' => 2,
            'instant_book_enabled' => true,
            'cancellation_policy' => 'flexible',
            'hold_duration_minutes' => 15,

            // Host Info
            'host' => [
                'name' => 'Alemayehu Tadesse',
                'host_type' => 'Superhost • Individual Owner',
                'response_rate_pct' => 100,
                'avg_response_minutes' => 12,
                'payout_schedule' => 'after_checkin',
                'avatar_initials' => 'AT',
            ],

            // Bed Configurations (SRS Section 43)
            'beds' => [
                ['room' => 'Bedroom 1', 'type' => 'King Bed', 'count' => 1, 'icon' => '🛏️'],
                ['room' => 'Bedroom 2', 'type' => 'Queen Bed', 'count' => 1, 'icon' => '🛏️'],
                ['room' => 'Bedroom 3', 'type' => 'Single Beds', 'count' => 2, 'icon' => '🛏️🛏️'],
                ['room' => 'Living Room', 'type' => 'Sofa Bed', 'count' => 1, 'icon' => '🛋️'],
            ],

            // House Rules (SRS Section 60)
            'house_rules' => [
                'check_in' => '14:00',
                'check_out' => '11:00',
                'quiet_hours' => '22:00 – 07:00',
                'smoking' => false,
                'parties' => false,
                'children' => true,
                'pets' => 'Allowed upon prior host confirmation',
                'additional_rules' => 'Please turn off the air conditioning when leaving the premises. Shoes off in carpeted master bedroom.',
            ],

            // Digital House Manual & Key Access (SRS Section 61)
            'house_manual' => [
                'wifi_ssid' => 'Villa-Bole-Fiber-5G',
                'wifi_password' => 'Medhanialem#Safe2026',
                'check_in_method' => 'Smart Lock Keypad',
                'keypad_code' => '8492#',
                'generator_guide' => '30kVA Perkins automatic diesel generator starts automatically within 12 seconds of EEP grid outage.',
                'parking' => 'Gated driveway parking for up to 3 vehicles with 24/7 security guard.',
                'is_unlocked' => false,
            ],

            // Host Local Guide (SRS Section 62)
            'local_guides' => [
                [
                    'category' => 'restaurant',
                    'icon' => '🍲',
                    'name' => 'Yod Abyssinia Cultural Restaurant',
                    'distance' => '800m walk',
                    'tip' => 'Authentic Ethiopian dining with live traditional music and dance performances every evening from 7 PM.',
                ],
                [
                    'category' => 'shop',
                    'icon' => '☕',
                    'name' => 'Tomoca Coffee Bole Medhanialem',
                    'distance' => '400m walk',
                    'tip' => 'Historic Italian-style coffee bar roastery serving the finest Ethiopian single-origin Harrar espresso.',
                ],
                [
                    'category' => 'transport',
                    'icon' => '✈️',
                    'name' => 'Bole International Airport (ADD)',
                    'distance' => '3.2 km (8 min drive)',
                    'tip' => 'Terminal 2 handles all international departures. Ask host for reliable 24/7 airport shuttle arrangement.',
                ],
                [
                    'category' => 'hospital',
                    'icon' => '🏥',
                    'name' => 'Korean Hospital (MCM General Hospital)',
                    'distance' => '4.5 km',
                    'tip' => 'Full-service international hospital with English, French, and Amharic speaking medical emergency staff.',
                ],
            ],

            // Dynamic Pricing Seasonal Rules (SRS Section 51)
            'seasonal_rules' => [
                [
                    'name' => 'Meskel Flower Festival Season',
                    'start_date' => '2026-09-24',
                    'end_date' => '2026-09-29',
                    'rate_multiplier' => 1.25,
                    'min_stay_nights' => 3,
                ],
                [
                    'name' => 'Timket Epiphany Celebration (Jan)',
                    'start_date' => '2027-01-17',
                    'end_date' => '2027-01-22',
                    'nightly_rate_etb' => 11000.0,
                    'min_stay_nights' => 5,
                ],
            ],
        ];
    }

    /**
     * Browse, search and filter eligible short-stay properties.
     */
    public function index(Request $request): View
    {
        $query = Listing::query()
            ->where('transaction_mode', 'STAY')
            ->where(function ($q) {
                $q->where('status', ListingStatus::Published->value)
                  ->orWhere('status', 'published');
            })
            ->with([
                'property.images',
                'property.city',
                'property.subcity',
                'property.propertyType',
                'property.amenities',
                'stayProfile.host.user',
                'stayProfile.bedConfigurations',
                'stayProfile.unitTypes.units',
            ]);

        $filters = [
            'location' => trim((string) $request->input('location', '')),
            'guests' => $request->filled('guests') ? (int) $request->input('guests') : null,
            'min_price' => $request->filled('min_price') ? (float) $request->input('min_price') : null,
            'max_price' => $request->filled('max_price') ? (float) $request->input('max_price') : null,
            'check_in' => $request->input('check_in'),
            'check_out' => $request->input('check_out'),
            'property_type' => $request->input('property_type'),
        ];

        // 1. Filter by location keyword (city, subcity, property title, or address)
        if ($filters['location'] !== '') {
            $loc = $filters['location'];
            $query->whereHas('property', function ($p) use ($loc) {
                $p->where('title_en', 'like', "%{$loc}%")
                  ->orWhere('title_am', 'like', "%{$loc}%")
                  ->orWhere('address_line', 'like', "%{$loc}%")
                  ->orWhereHas('city', fn ($c) => $c->where('name_en', 'like', "%{$loc}%")->orWhere('name_am', 'like', "%{$loc}%"))
                  ->orWhereHas('subcity', fn ($s) => $s->where('name_en', 'like', "%{$loc}%")->orWhere('name_am', 'like', "%{$loc}%"));
            });
        }

        // 2. Filter by guest capacity
        if ($filters['guests'] !== null && $filters['guests'] > 0) {
            $guests = $filters['guests'];
            $query->whereHas('stayProfile', fn ($sp) => $sp->where('max_guests', '>=', $guests));
        }

        // 3. Filter by nightly price
        if ($filters['min_price'] !== null) {
            $minPrice = $filters['min_price'];
            $query->where(function ($q) use ($minPrice) {
                $q->where('price_etb', '>=', $minPrice)
                  ->orWhereHas('stayProfile', fn ($sp) => $sp->where('base_nightly_rate_etb', '>=', $minPrice));
            });
        }
        if ($filters['max_price'] !== null) {
            $maxPrice = $filters['max_price'];
            $query->where(function ($q) use ($maxPrice) {
                $q->where('price_etb', '<=', $maxPrice)
                  ->orWhereHas('stayProfile', fn ($sp) => $sp->where('base_nightly_rate_etb', '<=', $maxPrice));
            });
        }

        // 4. Filter by property type
        if (! empty($filters['property_type'])) {
            $type = $filters['property_type'];
            $query->whereHas('property.propertyType', fn ($pt) => $pt->where('slug', $type)->orWhere('code', $type));
        }

        // 5. Authoritative availability filtering for requested dates
        if (! empty($filters['check_in']) && ! empty($filters['check_out'])) {
            try {
                $checkIn = CarbonImmutable::parse($filters['check_in'])->toDateString();
                $checkOut = CarbonImmutable::parse($filters['check_out'])->toDateString();

                if ($checkOut > $checkIn) {
                    $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

                    // Reject any listing whose units have booked or blocked calendar entries in this range
                    $query->whereDoesntHave('stayProfile.unitTypes.units.availabilityCalendars', function ($cal) use ($checkIn, $lastNight) {
                        $cal->whereBetween('calendar_date', [$checkIn, $lastNight])
                            ->whereIn('status', ['booked', 'held_pending_payment', 'blocked_by_host', 'external_ical_blocked']);
                    });
                }
            } catch (\Throwable) {
                // Invalid dates provided, skip date filtering
            }
        }

        $paginator = $query->latest('id')->paginate(12)->withQueryString();

        $stays = $paginator->getCollection()->map(function (Listing $listing): array {
            $prop = $listing->property;
            $profile = $listing->stayProfile;
            $host = $profile?->host;
            $cover = $prop?->images?->firstWhere('is_cover', true) ?? $prop?->images?->first();

            $locationName = $prop?->subcity?->name_en
                ? "{$prop->subcity->name_en}, " . ($prop->city?->name_en ?? 'Addis Ababa')
                : ($prop?->city?->name_en ?? 'Addis Ababa, Ethiopia');

            $bedsTotal = $profile?->bedConfigurations?->sum('bed_count') ?? 0;
            $bedDesc = $bedsTotal > 0 ? "{$bedsTotal} " . Str::plural('bed', $bedsTotal) : null;
            $bedroomDesc = $prop?->bedrooms ? "{$prop->bedrooms} " . Str::plural('bedroom', $prop->bedrooms) : null;
            $roomsOverview = implode(' • ', array_filter([$bedroomDesc, $bedDesc]));

            $effectiveNightlyRate = (float) ($profile?->base_nightly_rate_etb ?? $listing->price_etb);

            return [
                'id' => $listing->id,
                'slug' => $listing->slug,
                'property_code' => $prop?->property_code,
                'title' => $prop?->title_en ?? $listing->slug,
                'title_am' => $prop?->title_am,
                'property_type' => $prop?->propertyType?->name_en ? 'Serviced ' . $prop->propertyType->name_en : 'Serviced Villa',
                'location' => $locationName,
                'nightly_price_etb' => $effectiveNightlyRate,
                'price_formatted' => EtbFormatter::format($effectiveNightlyRate),
                'max_guests' => (int) ($profile?->max_guests ?? 4),
                'bedrooms' => (int) ($prop?->bedrooms ?? 0),
                'rooms_overview' => $roomsOverview,
                'verification_level' => (int) ($prop?->verification_level ?? 3),
                'is_superhost' => (bool) $host?->is_superhost,
                'host_name' => $host?->display_name ?? 'Verified Host',
                'rating' => (float) ($host?->average_rating ?: 4.95),
                'reviews_count' => (int) ($host?->reviews_count ?: 16),
                'instant_book_enabled' => (bool) ($profile?->instant_book_enabled ?? true),
                'cancellation_policy' => $profile?->cancellation_policy ?? 'flexible',
                'image_url' => $cover ? asset('storage/' . $cover->original_sanitized_path) : null,
                'amenities' => $prop?->amenities?->take(3)->pluck('name_en')->all() ?: ['Backup Generator', 'High-Speed Wi-Fi', 'Gated Parking'],
                'url' => route('stays.show', $listing->slug),
            ];
        })->values()->all();

        return view('stays.index', [
            'paginator' => $paginator,
            'stays' => $stays,
            'filters' => $filters,
            'totalCount' => $paginator->total(),
        ]);
    }

    /**
     * Display the full Short-Stay property detail page with interactive widgets.
     */
    public function show(string $slug, AuthorizeHouseManualAccessAction $houseManualAuth): View
    {
        $listing = Listing::query()
            ->where('slug', $slug)
            ->where('transaction_mode', 'STAY')
            ->with([
                'property.images',
                'property.city',
                'property.subcity',
                'property.propertyType',
                'property.amenities',
                'stayProfile.host.user',
                'stayProfile.bedConfigurations',
                'stayProfile.houseRules',
                'stayProfile.houseManual',
                'stayProfile.localGuides',
                'stayProfile.seasonalPrices',
                'stayProfile.unitTypes.units',
            ])
            ->first();

        if ($listing && $listing->stayProfile) {
            $isPublished = in_array(
                $listing->status instanceof ListingStatus ? $listing->status->value : (string) $listing->status,
                ['published', ListingStatus::Published->value],
                true
            );

            if (! $isPublished) {
                abort(404, 'Stay listing is not active or published.');
            }

            $profile = $listing->stayProfile;
            $property = $listing->property;
            $host = $profile->host;

            // Secure House Manual authorization
            $user = auth()->user();
            $canAccessManual = $houseManualAuth->canAccess($profile, $user);

            $manualData = [
                'is_unlocked' => $canAccessManual,
                'wifi_ssid' => $canAccessManual ? ($profile->houseManual?->wifi_ssid ?? 'N/A') : '🔒 Unlocked after booking confirmation',
                'wifi_password' => $canAccessManual ? ($profile->houseManual?->getDecryptedWifiPassword() ?? 'N/A') : '••••••••••••',
                'check_in_method' => $profile->houseManual?->check_in_method ?? 'host_greets',
                'access_instructions' => $canAccessManual ? ($profile->houseManual?->access_instructions_en ?? 'Host will meet you at property.') : 'Access instructions and keys are provided automatically to confirmed guests.',
                'generator_guide' => $canAccessManual ? ($profile->houseManual?->appliance_and_ac_guide_en ?? 'Backup generator starts automatically during outages.') : 'Backup utilities instructions unlock upon confirmation.',
                'parking' => $canAccessManual ? ($profile->houseManual?->parking_instructions_en ?? 'Gated parking on premises.') : 'Parking and gate instructions unlock upon confirmation.',
                'emergency_contact' => $canAccessManual ? ($profile->houseManual?->emergency_contact_phone ?? $host?->user?->phone) : 'Available upon booking confirmation',
            ];

            $stay = [
                'id' => $listing->id,
                'listing_id' => $listing->id,
                'slug' => $listing->slug,
                'property_code' => $property?->property_code ?? 'ETH-STAY-'.$listing->id,
                'title_en' => $property?->title_en ?? $listing->slug,
                'title_am' => $property?->title_am ?? '',
                'description_en' => $property?->description_en ?? '',
                'accommodation_model' => $profile->accommodation_model,
                'property_type' => 'STAY • '.($property?->propertyType?->name_en ? 'Serviced '.$property->propertyType->name_en : 'Serviced Villa'),
                'neighborhood' => trim(($property?->subcity?->name_en ?? '').', '.($property?->city?->name_en ?? 'Addis Ababa'), ', '),
                'rating' => (float) ($host?->average_rating ?: 4.95),
                'reviews_count' => (int) ($host?->reviews_count ?: 12),
                'is_superhost' => (bool) $host?->is_superhost,
                'verification_level' => (int) ($property?->verification_level ?: 3),

                // Pricing Tokens
                'base_nightly_rate_etb' => (float) $profile->base_nightly_rate_etb,
                'weekend_nightly_rate_etb' => $profile->weekend_nightly_rate_etb ? (float) $profile->weekend_nightly_rate_etb : (float) $profile->base_nightly_rate_etb,
                'weekly_discount_pct' => (float) $profile->weekly_discount_pct,
                'monthly_discount_pct' => (float) $profile->monthly_discount_pct,
                'cleaning_fee_etb' => (float) $profile->cleaning_fee_etb,
                'security_deposit_etb' => (float) $profile->security_deposit_etb,
                'guest_service_fee_pct' => (float) $profile->guest_service_fee_pct,
                'tourism_tax_pct' => (float) $profile->tourism_tax_pct,

                // Stay Limits
                'min_stay_nights' => (int) $profile->min_stay_nights,
                'max_stay_nights' => (int) $profile->max_stay_nights,
                'check_in_after_time' => $profile->check_in_after_time ?? '14:00',
                'check_out_before_time' => $profile->check_out_before_time ?? '11:00',
                'max_guests' => (int) $profile->max_guests,
                'max_adults' => (int) $profile->max_adults,
                'max_children' => (int) $profile->max_children,
                'instant_book_enabled' => (bool) $profile->instant_book_enabled,
                'cancellation_policy' => $profile->cancellation_policy ?? 'flexible',
                'hold_duration_minutes' => (int) ($profile->hold_duration_minutes ?: 15),

                // Host
                'host' => [
                    'name' => $host?->display_name ?? $host?->user?->name ?? 'Host',
                    'host_type' => $host?->is_superhost ? 'Superhost • '.($host->host_type ?? 'Owner') : ($host->host_type ?? 'Host'),
                    'response_rate_pct' => (int) ($host?->response_rate_pct ?: 100),
                    'avg_response_minutes' => (int) ($host?->avg_response_minutes ?: 15),
                    'payout_schedule' => $host?->payout_schedule ?? 'after_checkin',
                    'avatar_initials' => strtoupper(substr($host?->display_name ?? 'H', 0, 2)),
                ],

                // Beds
                'beds' => $profile->bedConfigurations->map(fn ($b) => [
                    'room' => $b->room_label_en,
                    'type' => ucfirst(str_replace('_', ' ', $b->bed_type)),
                    'count' => $b->bed_count,
                    'icon' => '🛏️',
                ])->all(),

                // House rules
                'house_rules' => [
                    'check_in' => $profile->check_in_after_time ?? '14:00',
                    'check_out' => $profile->check_out_before_time ?? '11:00',
                    'quiet_hours' => ($profile->houseRules?->quiet_hours_start ?? '22:00').' – '.($profile->houseRules?->quiet_hours_end ?? '07:00'),
                    'smoking' => (bool) $profile->houseRules?->smoking_allowed,
                    'parties' => (bool) $profile->houseRules?->parties_allowed,
                    'children' => (bool) $profile->houseRules?->children_allowed,
                    'pets' => $profile->allows_pets ? 'Allowed with prior notification' : 'Pets not allowed',
                    'additional_rules' => $profile->houseRules?->additional_rules_en ?? '',
                ],

                // House manual
                'house_manual' => $manualData,

                // Local guides
                'local_guides' => $profile->localGuides->map(fn ($g) => [
                    'category' => $g->category,
                    'icon' => match ($g->category) {
                        'restaurant' => '🍲',
                        'shop' => '☕',
                        'hospital' => '🏥',
                        'transport' => '✈️',
                        default => '📍',
                    },
                    'name' => $g->place_name,
                    'distance' => $g->distance_note ?? '',
                    'tip' => $g->host_tip_en ?? '',
                ])->all(),

                // Seasonal rules
                'seasonal_rules' => $profile->seasonalPrices->map(fn ($s) => [
                    'name' => $s->name,
                    'start_date' => $s->start_date->toDateString(),
                    'end_date' => $s->end_date->toDateString(),
                    'rate_multiplier' => (float) $s->rate_multiplier,
                    'nightly_rate_etb' => $s->nightly_rate_etb ? (float) $s->nightly_rate_etb : null,
                    'min_stay_nights' => $s->min_stay_nights,
                ])->all(),

                // Amenities
                'amenities' => $property?->amenities?->map(fn ($a) => [
                    'name' => $a->name_en,
                    'code' => $a->code,
                ])->all() ?: [],

                // Images
                'images' => $property?->images?->map(fn ($img) => [
                    'url' => asset('storage/'.$img->original_sanitized_path),
                    'is_cover' => (bool) $img->is_cover,
                    'caption' => $img->caption_en ?? '',
                ])->all() ?: [],

                // First Unit for availability calendar binding
                'unit_id' => $profile->unitTypes->first()?->units->first()?->id ?? 1,
            ];
        } else {
            abort(404, 'Stay listing not found or unavailable.');
        }

        return view('stays.show', compact('stay'));
    }

    /**
     * Backend API: Execute ResolveDynamicStayQuoteAction live.
     */
    public function calculateQuote(Request $request, ResolveDynamicStayQuoteAction $action): JsonResponse
    {
        $validated = $request->validate([
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'check_out_date' => ['required', 'date_format:Y-m-d', 'after:check_in_date'],
            'slug' => ['nullable', 'string'],
        ]);

        $listing = null;
        if (! empty($validated['slug'])) {
            $listing = Listing::query()->where('slug', $validated['slug'])->with('stayProfile.seasonalPrices')->first();
        }

        $stay = $listing && $listing->stayProfile ? [
            'base_nightly_rate_etb' => (float) $listing->stayProfile->base_nightly_rate_etb,
            'weekend_nightly_rate_etb' => (float) ($listing->stayProfile->weekend_nightly_rate_etb ?? $listing->stayProfile->base_nightly_rate_etb),
            'weekly_discount_pct' => (float) $listing->stayProfile->weekly_discount_pct,
            'monthly_discount_pct' => (float) $listing->stayProfile->monthly_discount_pct,
            'cleaning_fee_etb' => (float) $listing->stayProfile->cleaning_fee_etb,
            'security_deposit_etb' => (float) $listing->stayProfile->security_deposit_etb,
            'guest_service_fee_pct' => (float) $listing->stayProfile->guest_service_fee_pct,
            'tourism_tax_pct' => (float) $listing->stayProfile->tourism_tax_pct,
            'min_stay_nights' => (int) $listing->stayProfile->min_stay_nights,
            'max_stay_nights' => (int) $listing->stayProfile->max_stay_nights,
            'seasonal_rules' => $listing->stayProfile->seasonalPrices->map(fn ($s) => [
                'name' => $s->name,
                'start_date' => $s->start_date->toDateString(),
                'end_date' => $s->end_date->toDateString(),
                'rate_multiplier' => (float) $s->rate_multiplier,
                'nightly_rate_etb' => $s->nightly_rate_etb ? (float) $s->nightly_rate_etb : null,
                'min_stay_nights' => $s->min_stay_nights,
            ])->all(),
        ] : $this->getShowcaseProfile();

        try {
            $quote = $action->execute(
                checkInDate: $validated['check_in_date'],
                checkOutDate: $validated['check_out_date'],
                profile: [
                    'base_nightly_rate_etb' => $stay['base_nightly_rate_etb'],
                    'weekend_nightly_rate_etb' => $stay['weekend_nightly_rate_etb'],
                    'weekly_discount_pct' => $stay['weekly_discount_pct'],
                    'monthly_discount_pct' => $stay['monthly_discount_pct'],
                    'cleaning_fee_etb' => $stay['cleaning_fee_etb'],
                    'security_deposit_etb' => $stay['security_deposit_etb'],
                    'guest_service_fee_pct' => $stay['guest_service_fee_pct'],
                    'tourism_tax_pct' => $stay['tourism_tax_pct'],
                    'min_stay_nights' => $stay['min_stay_nights'],
                    'max_stay_nights' => $stay['max_stay_nights'],
                ],
                calendarOverridesByDate: [],
                seasonalRules: $stay['seasonal_rules']
            );

            return response()->json([
                'success' => true,
                'quote' => $quote,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Backend API: Execute CalculateCancellationRefundAction live.
     */
    public function calculateRefund(Request $request, CalculateCancellationRefundAction $action): JsonResponse
    {
        $validated = $request->validate([
            'policy' => ['required', 'string', 'in:flexible,moderate,strict'],
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'accommodation_net_subtotal_etb' => ['required', 'numeric', 'min:0'],
            'cleaning_fee_etb' => ['required', 'numeric', 'min:0'],
            'cancelled_at' => ['nullable', 'date'],
        ]);

        $result = $action->execute(
            policy: $validated['policy'],
            checkInDate: $validated['check_in_date'],
            accommodationNetSubtotalEtb: (float) $validated['accommodation_net_subtotal_etb'],
            cleaningFeeEtb: (float) $validated['cleaning_fee_etb'],
            cancelledAt: $validated['cancelled_at'] ?? null
        );

        return response()->json([
            'success' => true,
            'refund' => $result,
        ]);
    }

    /**
     * Backend API: Create 15-Minute Booking Hold (SRS §§49, 191, 193).
     */
    public function createHold(Request $request, CreateBookingHoldAction $action): JsonResponse
    {
        $validated = $request->validate([
            'check_in_date' => ['required', 'date_format:Y-m-d'],
            'check_out_date' => ['required', 'date_format:Y-m-d', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'infants' => ['nullable', 'integer', 'min:0'],
            'pets' => ['nullable', 'integer', 'min:0'],
            'listing_id' => ['nullable', 'integer'],
            'accommodation_unit_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'slug' => ['nullable', 'string'],
        ]);

        // Resolve listing
        $listingId = $validated['listing_id'] ?? null;
        if (! $listingId && ! empty($validated['slug'])) {
            $listingId = Listing::query()->where('slug', $validated['slug'])->value('id');
        }
        if (! $listingId) {
            $listingId = Listing::query()->where('transaction_mode', 'STAY')->value('id');
        }

        $listing = $listingId ? Listing::query()->with(['property', 'stayProfile.host'])->find($listingId) : null;
        $hostUserId = $listing?->property?->owner_id ?? $listing?->stayProfile?->host?->user_id;

        // Resolve user / guest
        $guest = $request->user();
        if (! $guest) {
            $guest = User::query()
                ->where('status', 'active')
                ->when($hostUserId, fn ($q) => $q->where('id', '!=', $hostUserId))
                ->first()
                ?? User::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Guest User',
                    'phone' => '+251911000111',
                    'status' => 'active',
                ]);
        }

        // If a real DB listing exists, execute real CreateBookingHoldAction
        if ($listingId) {
            $unitId = $validated['accommodation_unit_id'] ?? AccommodationUnit::query()->whereHas('unitType', fn ($q) => $q->where('stay_listing_profile_id', function ($sub) use ($listingId) {
                $sub->select('id')->from('stay_listing_profiles')->where('listing_id', $listingId);
            }))->value('id');

            if ($unitId) {
                $booking = $action->execute($guest, [
                    'listing_id' => (int) $listingId,
                    'accommodation_unit_id' => (int) $unitId,
                    'check_in_date' => $validated['check_in_date'],
                    'check_out_date' => $validated['check_out_date'],
                    'adults_count' => (int) $validated['adults'],
                    'children_count' => (int) ($validated['children'] ?? 0),
                    'infants_count' => (int) ($validated['infants'] ?? 0),
                    'pets_count' => (int) ($validated['pets'] ?? 0),
                    'idempotency_key' => $validated['idempotency_key'] ?? ('IDEMP-'.Str::uuid()->toString()),
                ]);

                $secondsRemaining = $booking->hold_expires_at
                    ? max(0, (int) round(now()->diffInSeconds($booking->hold_expires_at, false)))
                    : 900;

                return response()->json([
                    'success' => true,
                    'booking_code' => $booking->booking_code,
                    'idempotency_key' => $booking->idempotency_key,
                    'status' => $booking->status->value,
                    'hold_duration_minutes' => 15,
                    'hold_expires_at' => $booking->hold_expires_at?->toIso8601String(),
                    'hold_seconds_remaining' => $secondsRemaining,
                    'total_amount_etb' => (float) $booking->total_amount_etb,
                    'payment_instructions' => [
                        'telebirr' => 'Pay to Telebirr Merchant Code 492019',
                        'cbe_birr' => 'Transfer to Commercial Bank of Ethiopia A/C 1000293849102',
                    ],
                    'message' => 'Temporary inventory hold active. Room dates locked for 15 minutes.',
                ], 200);
            }
        }

        // Fallback mock simulation for isolated showcase mode
        $bookingCode = 'BK-'.date('Y').'-'.str_pad((string) random_int(1000, 99999), 7, '0', STR_PAD_LEFT);
        $idempotencyKey = $validated['idempotency_key'] ?? ('IDEMP-'.Str::uuid()->toString());
        $expiresAt = CarbonImmutable::now()->addMinutes(15);

        return response()->json([
            'success' => true,
            'booking_code' => $bookingCode,
            'idempotency_key' => $idempotencyKey,
            'status' => 'awaiting_payment',
            'hold_duration_minutes' => 15,
            'hold_expires_at' => $expiresAt->toIso8601String(),
            'hold_seconds_remaining' => 900,
            'payment_instructions' => [
                'telebirr' => 'Pay to Telebirr Merchant Code 492019',
                'cbe_birr' => 'Transfer to Commercial Bank of Ethiopia A/C 1000293849102',
            ],
            'message' => 'Temporary inventory hold active. Room dates locked for 15 minutes.',
        ]);
    }

    /**
     * Backend API: Fetch monthly availability calendar data for an accommodation unit.
     */
    public function getCalendar(string $slug, Request $request, GetStayCalendarAvailabilityAction $action): JsonResponse
    {
        $listing = Listing::query()->where('slug', $slug)->with('stayProfile.unitTypes.units')->first();
        $unitId = $request->query('unit_id');

        $unit = $unitId
            ? AccommodationUnit::query()->find($unitId)
            : $listing?->stayProfile?->unitTypes?->first()?->units?->first();

        if (! $unit) {
            $unit = AccommodationUnit::query()->first();
        }

        if (! $unit) {
            $today = CarbonImmutable::today();
            $headingInfo = EthiopianCalendarConverter::formatDualMonthHeading($today->startOfMonth(), $today->endOfMonth());

            return response()->json([
                'success' => true,
                'unit_id' => 1,
                'unit_code' => 'VILLA-1',
                'year_month' => $today->format('Y-m'),
                'month_name' => $headingInfo['combined_heading'],
                'calendar_heading' => $headingInfo['combined_heading'],
                'gregorian_heading' => $headingInfo['gregorian_heading'],
                'ethiopian_heading' => $headingInfo['ethiopian_heading'],
                'ethiopian_heading_am' => $headingInfo['ethiopian_heading_am'],
                'days' => [],
                'legend' => [
                    'available' => 'Available',
                    'booked' => 'Booked',
                    'held_pending_payment' => 'Temporary Hold (15m)',
                    'blocked_by_host' => 'Blocked by Host',
                    'external_ical_blocked' => 'External Calendar Block',
                ],
            ]);
        }

        $year = $request->query('year');
        $month = $request->query('month');
        $yearMonth = null;
        if ($year && $month) {
            $yearMonth = sprintf('%04d-%02d', (int) $year, (int) $month);
        } elseif ($month && str_contains((string) $month, '-')) {
            $yearMonth = (string) $month;
        } elseif ($month) {
            $yearMonth = sprintf('%04d-%02d', (int) date('Y'), (int) $month);
        }

        $result = $action->executeForUnit($unit, $yearMonth);

        return response()->json($result);
    }

    /**
     * Backend API: Confirm a booking hold upon payment confirmation.
     */
    public function confirmBooking(Request $request, ConfirmBookingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'booking_code' => ['required', 'string', 'exists:bookings,booking_code'],
        ]);

        $booking = Booking::query()->where('booking_code', $validated['booking_code'])->firstOrFail();
        $confirmed = $action->execute($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed successfully.',
            'booking' => $confirmed,
        ]);
    }

    /**
     * Backend API: Cancel a booking and compute refund.
     */
    public function cancelBooking(Request $request, CancelBookingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'booking_code' => ['required', 'string', 'exists:bookings,booking_code'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = Booking::query()->where('booking_code', $validated['booking_code'])->firstOrFail();
        $user = $request->user() ?? $booking->guest;

        $result = $action->execute($booking, $user, $validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled.',
            'refund' => $result['refund'],
            'booking' => $result['booking'],
        ]);
    }

    /**
     * Download live RFC 5545 .ics calendar feed (SRS Section 57).
     */
    public function exportIcal(string $slug, Request $request): Response
    {
        $token = $request->query('token');

        if (! empty($token)) {
            $ics = IcalSyncService::exportFeedByToken((string) $token);

            return response($ics, 200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="'.$slug.'-calendar.ics"',
            ]);
        }

        $sampleReservations = [
            [
                'uid' => 'BK-2026-0001045',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-05',
                'summary' => 'Confirmed Guest Stay (Addis Ababa)',
            ],
            [
                'uid' => 'BK-2026-0001180',
                'start_date' => '2026-11-12',
                'end_date' => '2026-11-16',
                'summary' => 'Direct Host Hold (Maintenance)',
            ],
        ];

        $ics = IcalSyncService::generateExportIcs('ETH-AA-BOLE-RES-00001245', $sampleReservations);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-calendar.ics"',
        ]);
    }
}
