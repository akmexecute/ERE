<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Subscription\Enums\FeatureValueType;
use App\Domains\Subscription\Enums\SubscriberType;
use App\Domains\Subscription\Models\SubscriptionFeature;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\SubscriptionPlanFeatureValue;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubscriptionFeaturesAndPlansTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->admin = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Admin User',
            'phone' => '+251911000001',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $this->regularUser = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Regular Buyer',
            'phone' => '+251911000002',
            'status' => 'active',
        ]);
        $this->regularUser->assignRole('buyer');
    }

    public function test_admin_can_create_subscription_features_with_all_value_types(): void
    {
        $types = [
            ['key' => 'test.boolean_feat', 'type' => FeatureValueType::Boolean->value, 'default' => 'false'],
            ['key' => 'test.integer_feat', 'type' => FeatureValueType::Integer->value, 'default' => '10'],
            ['key' => 'test.unlimited_feat', 'type' => FeatureValueType::Unlimited->value, 'default' => 'unlimited'],
            ['key' => 'test.text_feat', 'type' => FeatureValueType::Text->value, 'default' => 'custom_tier'],
            ['key' => 'test.select_feat', 'type' => FeatureValueType::Select->value, 'default' => 'standard'],
        ];

        foreach ($types as $item) {
            $response = $this->actingAs($this->admin)->post(route('admin.subscriptions.features.store'), [
                'key' => $item['key'],
                'name_en' => 'Test ' . ucfirst($item['type']),
                'value_type' => $item['type'],
                'default_value' => $item['default'],
                'sort_order' => 50,
                'is_active' => true,
            ]);

            $response->assertRedirect(route('admin.subscriptions.features.index'));
            $this->assertDatabaseHas('subscription_features', [
                'key' => $item['key'],
                'value_type' => $item['type'],
                'default_value' => $item['default'],
            ]);
        }
    }

    public function test_admin_can_update_and_toggle_feature_status(): void
    {
        $feature = SubscriptionFeature::query()->where('key', 'property.virtual_tours')->firstOrFail();

        $response = $this->actingAs($this->admin)->put(route('admin.subscriptions.features.update', $feature), [
            'name_en' => 'Updated 360 Walkthroughs',
            'name_am' => 'የተሻሻለ 360 ጉብኝት',
            'name_om' => 'Daawwanna 360',
            'description_en' => 'Updated description text',
            'value_type' => FeatureValueType::Boolean->value,
            'default_value' => 'false',
            'sort_order' => 15,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.subscriptions.features.index'));
        $feature->refresh();
        $this->assertSame('Updated 360 Walkthroughs', $feature->name_en);

        // Toggle status
        $toggleResp = $this->actingAs($this->admin)->post(route('admin.subscriptions.features.toggle', $feature));
        $toggleResp->assertRedirect(route('admin.subscriptions.features.index'));
        $feature->refresh();
        $this->assertFalse($feature->is_active);
    }

    public function test_admin_can_create_plan_and_assign_feature_values(): void
    {
        $featVirtualTour = SubscriptionFeature::query()->where('key', 'property.virtual_tours')->firstOrFail();
        $featMaxListings = SubscriptionFeature::query()->where('key', 'property.max_active_listings')->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('admin.subscriptions.plans.store'), [
            'code' => 'broker_vip',
            'name_en' => 'VIP Broker Plan',
            'name_am' => 'ቪአይፒ የደላላ እቅድ',
            'subscriber_type' => SubscriberType::Agent->value,
            'price_etb' => 2500.00,
            'duration_days' => 30,
            'sort_order' => 25,
            'is_active' => true,
            'is_public' => true,
            'feature_values' => [
                $featVirtualTour->id => 'true',
                $featMaxListings->id => '50',
            ],
        ]);

        $response->assertRedirect(route('admin.subscriptions.plans.index'));

        $plan = SubscriptionPlan::query()->where('code', 'broker_vip')->firstOrFail();
        $this->assertEquals(2500.00, (float) $plan->price_etb);

        $this->assertDatabaseHas('subscription_plan_feature_values', [
            'plan_id' => $plan->id,
            'feature_id' => $featVirtualTour->id,
            'value' => 'true',
        ]);
        $this->assertDatabaseHas('subscription_plan_feature_values', [
            'plan_id' => $plan->id,
            'feature_id' => $featMaxListings->id,
            'value' => '50',
        ]);
    }

    public function test_unauthorized_user_cannot_manage_subscription_features_or_plans(): void
    {
        $feature = SubscriptionFeature::firstOrFail();
        $plan = SubscriptionPlan::firstOrFail();

        // Regular buyer cannot access admin endpoints
        $this->actingAs($this->regularUser)
            ->get(route('admin.subscriptions.features.index'))
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->post(route('admin.subscriptions.features.store'), ['key' => 'hack.key'])
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->get(route('admin.subscriptions.plans.index'))
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->put(route('admin.subscriptions.plans.update', $plan), ['name_en' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_public_subscriptions_index_renders_all_plans_and_feature_matrix(): void
    {
        // Unauthenticated visitor can view subscription options
        $response = $this->get(route('subscriptions.index'));

        $response->assertStatus(200);
        $response->assertSee('Scale Your Real Estate Reach Across Ethiopia');
        $response->assertSee('Standard Free');
        $response->assertSee('Agent Pro');
        $response->assertSee('Agency');
        $response->assertSee('Developer');
        $response->assertSee('Feature Comparison Matrix');
        $response->assertSee('Configure Subscription Checkout');
    }
}
