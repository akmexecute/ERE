<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\Cleaner;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Models\DamageClaim;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\Host\Models\Host;
use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Review\Models\StayReview;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\CheckIn;
use App\Domains\Stay\Models\CheckOut;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HostOperationsAndReviewsPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $hostUser;
    private User $guestUser;
    private Host $host;
    private Property $property;
    private Listing $listing;
    private StayListingProfile $profile;
    private AccommodationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Host',
            'phone' => '+251911223344',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $this->guestUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Guest',
            'phone' => '+251922334455',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'host_type' => 'individual_owner',
            'display_name' => 'Sara Bole Host',
            'is_superhost' => true,
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001245',
            'owner_id' => $this->hostUser->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Modern Villa Bole',
            'status' => PropertyStatus::Available->value,
            'verification_level' => 3,
        ]);

        $listingType = ListingType::query()->firstOrCreate(
            ['code' => 'SHORT_STAY_ENTIRE'],
            ['slug' => 'short-stay-entire', 'name_en' => 'Short Stay (Entire Place)', 'transaction_mode' => 'STAY', 'is_active' => true]
        );

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-STAY-001',
            'slug' => 'modern-villa-bole',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->hostUser->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'STAY',
            'price_etb' => 8500,
            'currency' => 'ETB',
            'status' => ListingStatus::Published,
            'published_at' => now(),
        ]);

        $this->profile = StayListingProfile::query()->create([
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'accommodation_model' => 'entire_place',
            'base_nightly_rate_etb' => 8500,
        ]);

        $unitType = AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Villa',
            'max_guests' => 6,
            'base_nightly_rate_etb' => 8500,
        ]);

        $this->unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-A',
            'status' => 'ready',
        ]);
    }

    public function test_host_dashboard_renders_successfully(): void
    {
        $response = $this->get('/host/dashboard');

        $response->assertStatus(200)
            ->assertSee('Host Management', false)
            ->assertSee('Revenue Dashboard', false)
            ->assertSee('Housekeeping & Turnover Pipeline', false)
            ->assertSee('Two-Way Reputation', false)
            ->assertSee('Security Deposit Custody', false);
    }

    public function test_api_settle_deposit_accepts_damage_claim(): void
    {
        $response = $this->postJson('/api/host/settle-deposit', [
            'held_deposit_etb' => 5000,
            'claimed_damage_etb' => 1500,
            'decision' => 'accept',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'deposit_status' => 'partially_deducted',
                    'deducted_amount_etb' => 1500,
                    'released_to_guest_etb' => 3500,
                    'dispute_opened' => false,
                ],
            ]);
    }

    public function test_api_settle_deposit_opens_dispute_when_contested(): void
    {
        $response = $this->postJson('/api/host/settle-deposit', [
            'held_deposit_etb' => 5000,
            'claimed_damage_etb' => 2000,
            'decision' => 'dispute',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'deposit_status' => 'disputed',
                    'dispute_opened' => true,
                    'dispute_stage' => 'evidence',
                ],
            ]);
    }

    public function test_api_evaluate_review_moderates_spam(): void
    {
        $response = $this->postJson('/api/host/evaluate-review', [
            'direction' => 'guest_to_host',
            'scores' => [
                'cleanliness' => 5,
                'accuracy' => 4,
                'communication' => 5,
                'location' => 4,
                'value' => 5,
            ],
            'comment' => 'Check out t.me/free_crypto for discounts',
            'counterparty_already_submitted' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'result' => [
                    'moderation_status' => 'flagged_spam',
                    'publish_both_now' => false,
                ],
            ]);
    }

    public function test_api_advance_cleaning_progresses_state_machine(): void
    {
        $response = $this->postJson('/api/host/advance-cleaning', [
            'current_status' => 'cleaning',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'new_status' => 'inspection',
                'can_advance' => true,
            ]);
    }
}
