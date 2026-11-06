<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use App\Domains\VirtualTour\Models\PropertyTourHotspot;
use App\Domains\VirtualTour\Models\PropertyTourScene;
use App\Domains\VirtualTour\Models\PropertyVirtualTour;
use App\Domains\VirtualTour\Services\VirtualTourService;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VirtualTourSecurityAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $freeOwner;
    private User $proOwner;
    private Property $freeProperty;
    private Property $proProperty;
    private SubscriptionPlan $agentProPlan;
    private SubscriptionPlan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->freePlan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $this->agentProPlan = SubscriptionPlan::where('code', 'agent_pro')->firstOrFail();

        $cat = PropertyCategory::firstOrCreate(['code' => 'RES'], ['name_en' => 'Residential', 'sort_order' => 1, 'is_active' => true]);
        $type = PropertyType::firstOrCreate(['code' => 'VILLA'], ['category_id' => $cat->id, 'name_en' => 'Villa', 'sort_order' => 1, 'is_active' => true]);

        // 1. Free Owner
        $this->freeOwner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Free Owner',
            'phone' => '+251911111111',
            'status' => 'active',
        ]);
        $this->freeOwner->assignRole('property_owner');
        $this->freeProperty = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-FREE-PROP-01',
            'title_en' => 'Standard Free Villa',
            'category_id' => $cat->id,
            'property_type_id' => $type->id,
            'owner_id' => $this->freeOwner->id,
            'created_by' => $this->freeOwner->id,
            'status' => PropertyStatus::Available,
        ]);

        // 2. Pro Owner with active Agent Pro subscription
        $this->proOwner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pro Owner',
            'phone' => '+251922222222',
            'status' => 'active',
        ]);
        $this->proOwner->assignRole('agent');
        $this->proProperty = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-PRO-PROP-02',
            'title_en' => 'Luxury Bole Villa',
            'category_id' => $cat->id,
            'property_type_id' => $type->id,
            'owner_id' => $this->proOwner->id,
            'created_by' => $this->proOwner->id,
            'status' => PropertyStatus::Available,
        ]);

        $snapshot = [];
        foreach ($this->agentProPlan->featureValues()->with('feature')->get() as $fv) {
            if ($fv->feature) {
                $snapshot[$fv->feature->key] = $fv->value;
            }
        }

        UserSubscription::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->proOwner->id,
            'plan_id' => $this->agentProPlan->id,
            'status' => SubscriptionStatus::Active,
            'price_etb' => 1200.00,
            'currency' => 'ETB',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'features_snapshot_json' => $snapshot,
        ]);
    }

    public function test_owner_without_virtual_tour_entitlement_cannot_create_virtual_tour(): void
    {
        $service = app(VirtualTourService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('360° Virtual Tour creation requires an eligible active subscription');

        $service->createTour($this->freeOwner, $this->freeProperty, [
            'title' => 'Unauthorized Tour',
        ]);
    }

    public function test_owner_with_agent_pro_entitlement_can_create_tour_workspace(): void
    {
        $service = app(VirtualTourService::class);

        $tour = $service->createTour($this->proOwner, $this->proProperty, [
            'title' => 'Luxury Bole Villa - 360 Walkthrough',
            'description' => 'Explore the open concept salon and master bedroom',
        ]);

        $this->assertNotNull($tour);
        $this->assertSame('Luxury Bole Villa - 360 Walkthrough', $tour->title);
        $this->assertTrue($tour->is_published);
        $this->assertSame($this->proProperty->id, $tour->property_id);
    }

    public function test_owner_can_upload_room_scenes_and_link_hotspots(): void
    {
        $service = app(VirtualTourService::class);

        $tour = $service->createTour($this->proOwner, $this->proProperty, [
            'title' => 'Luxury Bole Villa Tour',
        ]);

        // Upload Scene 1 (Living Room)
        $fakePano1 = UploadedFile::fake()->image('living_room_360.jpg', 600, 300);
        $scene1 = $service->addScene($this->proOwner, $tour, [
            'room_name' => 'Living Room',
            'initial_pitch' => 0.0,
            'initial_yaw' => 0.0,
            'is_default_entry' => true,
        ], $fakePano1);

        // Upload Scene 2 (Kitchen)
        $fakePano2 = UploadedFile::fake()->image('kitchen_360.jpg', 600, 300);
        $scene2 = $service->addScene($this->proOwner, $tour, [
            'room_name' => 'Kitchen',
            'initial_pitch' => -5.0,
            'initial_yaw' => 45.0,
            'is_default_entry' => false,
        ], $fakePano2);

        $this->assertSame(2, $tour->scenes()->count());
        $this->assertTrue($scene1->is_default_entry);

        // Add Hotspot from Living Room -> Kitchen
        $hotspot = $service->addHotspot($this->proOwner, $scene1, [
            'target_scene_id' => $scene2->id,
            'pitch' => -2.5,
            'yaw' => 50.0,
            'title' => 'Enter Kitchen',
        ]);

        $this->assertSame('Enter Kitchen', $hotspot->title);
        $this->assertEquals(-2.5, (float) $hotspot->pitch);
        $this->assertEquals(50.0, (float) $hotspot->yaw);
    }

    public function test_invalid_hotspot_coordinates_rejected(): void
    {
        $service = app(VirtualTourService::class);
        $tour = $service->createTour($this->proOwner, $this->proProperty, ['title' => 'Tour']);

        $file1 = UploadedFile::fake()->image('p1.jpg', 600, 300);
        $file2 = UploadedFile::fake()->image('p2.jpg', 600, 300);

        $scene1 = $service->addScene($this->proOwner, $tour, ['room_name' => 'Room 1'], $file1);
        $scene2 = $service->addScene($this->proOwner, $tour, ['room_name' => 'Room 2'], $file2);

        // Pitch > 90 degrees rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pitch must be between -90 and 90 degrees.');

        $service->addHotspot($this->proOwner, $scene1, [
            'target_scene_id' => $scene2->id,
            'pitch' => 95.0,
            'yaw' => 10.0,
        ]);
    }

    public function test_unauthenticated_visitor_can_view_published_virtual_tour_without_subscription(): void
    {
        $service = app(VirtualTourService::class);
        $tour = $service->createTour($this->proOwner, $this->proProperty, ['title' => 'Public Tour']);

        $file = UploadedFile::fake()->image('living.jpg', 600, 300);
        $service->addScene($this->proOwner, $tour, ['room_name' => 'Salon'], $file);

        // Public visitor (not authenticated) visits the tour endpoint
        $response = $this->get(route('virtual-tours.show', $tour->uuid));

        $response->assertStatus(200);
        $response->assertSee('Public 360° Virtual Walkthrough &middot; All Visitors Welcome', false);
        $response->assertSee($tour->title);

        // JSON format for viewer API
        $jsonResp = $this->getJson(route('virtual-tours.show', ['uuid' => $tour->uuid, 'format' => 'json']));
        $jsonResp->assertStatus(200);
        $jsonResp->assertJsonPath('tour.title', $tour->title);
    }

    public function test_published_virtual_tours_remain_publicly_viewable_after_owner_subscription_expires(): void
    {
        $service = app(VirtualTourService::class);
        $tour = $service->createTour($this->proOwner, $this->proProperty, ['title' => 'Grand Estate Tour']);

        $file = UploadedFile::fake()->image('estate.jpg', 600, 300);
        $service->addScene($this->proOwner, $tour, ['room_name' => 'Courtyard'], $file);

        // Owner's subscription expires
        UserSubscription::query()
            ->where('user_id', $this->proOwner->id)
            ->update([
                'status' => SubscriptionStatus::Expired,
                'expires_at' => now()->subDay(),
            ]);

        // Crucial requirement: Published tour STILL remains publicly viewable by visitors!
        $response = $this->get(route('virtual-tours.show', $tour->uuid));
        $response->assertStatus(200);
        $response->assertSee('Grand Estate Tour');
    }
}
