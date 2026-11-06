<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\GIS\Models\GisNeighborhoodHeatmap;
use App\Domains\GIS\Services\NeighborhoodHeatmapAggregationService;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PostGisNeighborhoodHeatmapTest extends TestCase
{
    use RefreshDatabase;

    private User $freeUser;
    private User $proUser;
    private Location $neighborhood;
    private PropertyCategory $category;
    private PropertyType $propertyType;
    private SubscriptionPlan $agentProPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->agentProPlan = SubscriptionPlan::where('code', 'agent_pro')->firstOrFail();

        $this->freeUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Free Buyer',
            'phone' => '+251911333333',
            'status' => 'active',
        ]);
        $this->freeUser->assignRole('buyer');

        $this->proUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pro Agent',
            'phone' => '+251911444444',
            'status' => 'active',
        ]);
        $this->proUser->assignRole('agent');

        // Grant Pro User the Agent Pro subscription
        $snapshot = [];
        foreach ($this->agentProPlan->featureValues()->with('feature')->get() as $fv) {
            if ($fv->feature) {
                $snapshot[$fv->feature->key] = $fv->value;
            }
        }
        UserSubscription::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->proUser->id,
            'plan_id' => $this->agentProPlan->id,
            'status' => SubscriptionStatus::Active,
            'price_etb' => 1200.00,
            'currency' => 'ETB',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'features_snapshot_json' => $snapshot,
        ]);

        $this->neighborhood = Location::query()->firstOrCreate(
            ['code' => 'ETH-AA-BOLE-01'],
            [
                'slug' => 'bole-medhanialem',
                'name_en' => 'Bole Medhanialem',
                'name_am' => 'ቦሌ መድኃኒዓለም',
                'level' => 'neighborhood',
                'latitude' => 9.0012,
                'longitude' => 38.7845,
                'is_active' => true,
            ]
        );

        $this->category = PropertyCategory::firstOrCreate(['code' => 'RES'], ['name_en' => 'Residential', 'sort_order' => 1, 'is_active' => true]);
        $this->propertyType = PropertyType::firstOrCreate(['code' => 'APT'], ['category_id' => $this->category->id, 'name_en' => 'Apartment', 'sort_order' => 1, 'is_active' => true]);
    }

    private function createSampleListing(int $priceEtb, int $areaSqm, string $mode = 'BUY'): Listing
    {
        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-PROP-' . Str::random(6),
            'title_en' => 'Sample Listing in Bole',
            'category_id' => $this->category->id,
            'property_type_id' => $this->propertyType->id,
            'neighborhood_id' => $this->neighborhood->id,
            'building_area_sqm' => $areaSqm,
            'land_area_sqm' => $areaSqm,
            'owner_id' => $this->proUser->id,
            'created_by' => $this->proUser->id,
            'status' => PropertyStatus::Available,
        ]);

        return Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-' . Str::random(6),
            'slug' => 'sample-bole-apt-' . Str::random(6),
            'property_id' => $prop->id,
            'listing_type_id' => \Illuminate\Support\Facades\DB::table('listing_types')->where('code', 'FOR_SALE')->value('id') ?? 1,
            'published_by_user_id' => $this->proUser->id,
            'transaction_mode' => $mode,
            'price_etb' => $priceEtb,
            'price_per_sqm_etb' => (float) ($priceEtb / $areaSqm),
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);
    }

    public function test_aggregation_calculates_asking_price_per_sqm_from_published_listings(): void
    {
        // Create 3 published listings in Bole Medhanialem with known ETB/m²
        // Listing 1: 5,000,000 ETB / 100 m² = 50,000 ETB/m²
        $this->createSampleListing(5_000_000, 100, 'BUY');
        // Listing 2: 7,000,000 ETB / 100 m² = 70,000 ETB/m²
        $this->createSampleListing(7_000_000, 100, 'BUY');
        // Listing 3: 9,000,000 ETB / 100 m² = 90,000 ETB/m²
        $this->createSampleListing(9_000_000, 100, 'BUY');

        $service = app(NeighborhoodHeatmapAggregationService::class);
        $results = $service->aggregate('BUY');

        $this->assertNotEmpty($results);
        $record = $results->firstWhere('location_id', $this->neighborhood->id);

        $this->assertNotNull($record);
        $this->assertSame(3, $record->sample_size);
        $this->assertEquals(70000.00, (float) $record->avg_price_per_sqm_etb);
        $this->assertEquals(70000.00, (float) $record->median_price_per_sqm_etb);
        $this->assertEquals(50000.00, (float) $record->min_price_per_sqm_etb);
        $this->assertEquals(90000.00, (float) $record->max_price_per_sqm_etb);
        $this->assertTrue($record->platform_asking_price_indicator);
    }

    public function test_aggregation_separates_transaction_modes_buy_rent_lease(): void
    {
        // 1 BUY listing @ 60,000 ETB/m²
        $this->createSampleListing(6_000_000, 100, 'BUY');

        // 1 RENT listing @ 500 ETB/m²
        $this->createSampleListing(50_000, 100, 'RENT');

        $service = app(NeighborhoodHeatmapAggregationService::class);
        $buyResults = $service->aggregate('BUY');
        $rentResults = $service->aggregate('RENT');

        $buyRecord = $buyResults->firstWhere('location_id', $this->neighborhood->id);
        $rentRecord = $rentResults->firstWhere('location_id', $this->neighborhood->id);

        $this->assertNotNull($buyRecord);
        $this->assertEquals(60000.00, (float) $buyRecord->avg_price_per_sqm_etb);

        $this->assertNotNull($rentRecord);
        $this->assertEquals(500.00, (float) $rentRecord->avg_price_per_sqm_etb);
    }

    public function test_geojson_export_includes_mandatory_attribution(): void
    {
        $this->createSampleListing(6_000_000, 100, 'BUY');

        $service = app(NeighborhoodHeatmapAggregationService::class);
        $geojson = $service->toGeoJson('BUY');

        $this->assertSame('FeatureCollection', $geojson['type']);
        $this->assertNotEmpty($geojson['features']);
        $this->assertTrue($geojson['metadata']['platform_asking_price_indicator']);
        $this->assertStringContainsString('Platform-Derived Market Indicator', $geojson['metadata']['attribution']);
    }

    public function test_api_neighborhood_heatmap_endpoint_requires_subscription_entitlement(): void
    {
        $this->createSampleListing(6_000_000, 100, 'BUY');

        // 1. Unauthenticated request -> 401
        $guestResp = $this->getJson('/api/gis/neighborhood-heatmap');
        $guestResp->assertStatus(401);

        // 2. Free User without entitlement -> 403 Forbidden with upgrade prompt
        $freeResp = $this->actingAs($this->freeUser)->getJson('/api/gis/neighborhood-heatmap');
        $freeResp->assertStatus(403);
        $freeResp->assertJsonPath('error', 'upgrade_required');
        $freeResp->assertJsonPath('upgrade_url', route('subscriptions.index'));

        // 3. Pro User with Agent Pro entitlement -> 200 OK with GeoJSON
        $proResp = $this->actingAs($this->proUser)->getJson('/api/gis/neighborhood-heatmap');
        $proResp->assertStatus(200);
        $proResp->assertJsonPath('type', 'FeatureCollection');
        $this->assertNotEmpty($proResp->json('features'));
    }
}
