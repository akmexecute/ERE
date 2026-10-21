<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Analytics\Models\MarketAnalyticsSnapshot;
use App\Domains\Analytics\Services\MarketAnalyticsCalculator;
use App\Domains\Engagement\Models\CollectionItem;
use App\Domains\Engagement\Models\Favorite;
use App\Domains\Engagement\Models\SavedCollection;
use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Domains\Inquiry\Actions\RevealSellerPhoneAction;
use App\Domains\Inquiry\Enums\InquiryStatus;
use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Inquiry\Models\Message;
use App\Domains\Inquiry\Models\MessageAttachment;
use App\Domains\Inquiry\Models\PhoneRevealLog;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Marketplace\Actions\ComparePropertiesMatrixAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class EngagementAndInquiryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Property $property;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(\Database\Seeders\EthiopianLocationsSeeder::class);

        $this->seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Kebede',
            'phone' => '+251911234567',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dawit Tadesse',
            'phone' => '+251922334455',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001245',
            'owner_id' => $this->seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Modern 4-Bed Villa in Bole Medhanialem',
            'title_am' => 'ዘመናዊ 4 መኝታ ቪላ ቦሌ መድኃኔዓለም',
            'status' => PropertyStatus::Available->value,
            'verification_level' => 3,
            'building_area_sqm' => 375,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'parking_spaces' => 2,
        ]);

        $listingType = ListingType::query()->where('transaction_mode', 'BUY')->firstOrFail();

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-00001001',
            'slug' => 'modern-4-bed-villa-in-bole-medhanialem',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 25_000_000,
            'price_per_sqm_etb' => 66_666.67,
            'status' => ListingStatus::Published,
            'phone_privacy_override' => null,
            'published_at' => now(),
        ]);
    }

    public function test_user_can_favorite_listing_and_manage_saved_collections(): void
    {
        // 1. Buyer favorites the listing
        $fav = Favorite::query()->create([
            'user_id' => $this->buyer->id,
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
        ]);

        $this->assertDatabaseHas('favorites', [
            'id' => $fav->id,
            'user_id' => $this->buyer->id,
            'listing_id' => $this->listing->id,
        ]);

        // Duplicate favorite throws QueryException
        $this->expectException(QueryException::class);
        Favorite::query()->create([
            'user_id' => $this->buyer->id,
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
        ]);
    }

    public function test_buyer_can_create_private_collection_with_items_and_notes(): void
    {
        $collection = SavedCollection::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->buyer->id,
            'name' => 'My Dream Homes in Addis',
            'description' => 'Villas for relocation',
            'is_private' => true,
        ]);

        $item = CollectionItem::query()->create([
            'collection_id' => $collection->id,
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'personal_note' => 'Spacious compound, close to Bole airport.',
        ]);

        $this->assertDatabaseHas('collection_items', [
            'id' => $item->id,
            'collection_id' => $collection->id,
            'listing_id' => $this->listing->id,
            'personal_note' => 'Spacious compound, close to Bole airport.',
        ]);

        $this->assertCount(1, $collection->items);
        $this->assertEquals('My Dream Homes in Addis', $item->collection->name);
    }

    public function test_four_property_comparison_matrix_identifies_best_value_metrics(): void
    {
        $action = new ComparePropertiesMatrixAction();

        $listingsData = [
            [
                'id' => 1,
                'property_code' => 'ETH-AA-BOLE-RES-00000001',
                'title' => 'Luxury Villa Bole',
                'location_label' => 'Bole, Addis Ababa',
                'price_etb' => 30_000_000,
                'area_sqm' => 500, // 60,000 ETB/m²
                'bedrooms' => 5,
                'bathrooms' => 5,
                'parking_spaces' => 4,
                'verification_level' => 4,
                'amenities' => ['Generator', 'Water Tank', 'Garden'],
            ],
            [
                'id' => 2,
                'property_code' => 'ETH-AA-YEKA-RES-00000002',
                'title' => 'Modern Villa Yeka',
                'location_label' => 'Yeka, Addis Ababa',
                'price_etb' => 20_000_000,
                'area_sqm' => 250, // 80,000 ETB/m²
                'bedrooms' => 3,
                'bathrooms' => 3,
                'parking_spaces' => 2,
                'verification_level' => 2,
                'amenities' => ['Water Tank'],
            ],
            [
                'id' => 3,
                'property_code' => 'ETH-AA-KIRK-RES-00000003',
                'title' => 'Apartment Kazanchis',
                'location_label' => 'Kirkos, Addis Ababa',
                'price_etb' => 15_000_000,
                'area_sqm' => 180, // 83,333 ETB/m²
                'bedrooms' => 3,
                'bathrooms' => 2,
                'parking_spaces' => 1,
                'verification_level' => 3,
                'amenities' => ['Elevator', 'Security'],
            ],
            [
                'id' => 4,
                'property_code' => 'ETH-AA-GUFA-RES-00000004',
                'title' => 'Suburban House Nifas Silk',
                'location_label' => 'Nifas Silk, Addis Ababa',
                'price_etb' => 18_000_000,
                'area_sqm' => 320, // 56,250 ETB/m²
                'bedrooms' => 4,
                'bathrooms' => 3,
                'parking_spaces' => 2,
                'verification_level' => 1,
                'amenities' => ['Garden'],
            ],
            [
                'id' => 5,
                'property_code' => 'ETH-AA-ARAD-RES-00000005',
                'title' => 'Fifth Property (Should be omitted)',
                'price_etb' => 50_000_000,
                'area_sqm' => 600,
            ],
        ];

        $result = $action->execute($listingsData);

        // Maximum 4 items
        $this->assertCount(4, $result['items']);

        // Best-value highlights:
        // Lowest price is #3 (15,000,000)
        $this->assertSame(3, $result['highlights']['lowest_price_id']);

        // Lowest price per sqm is #4 (18M / 320m² = 56,250 ETB/m²)
        $this->assertSame(4, $result['highlights']['lowest_price_per_sqm_id']);

        // Largest area is #1 (500 sqm)
        $this->assertSame(1, $result['highlights']['largest_area_id']);

        // Highest verification is #1 (Level 4)
        $this->assertSame(1, $result['highlights']['highest_verification_id']);

        // Formatted strings verified
        $this->assertStringContainsString('ETB', $result['items'][0]['price_formatted']);
        $this->assertStringContainsString('/ m²', $result['items'][0]['price_per_sqm_formatted']);
    }

    public function test_inquiry_lead_pipeline_and_contextual_messages(): void
    {
        $inquiry = Inquiry::query()->create([
            'uuid' => (string) Str::uuid(),
            'inquiry_code' => 'INQ-2026-0000482',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'lead_source' => 'website',
            'status' => InquiryStatus::New->value,
            'initial_message' => 'Is this property still available for viewing this week?',
            'unread_buyer_count' => 0,
            'unread_seller_count' => 1,
        ]);

        $this->assertDatabaseHas('inquiries', [
            'inquiry_code' => 'INQ-2026-0000482',
            'status' => 'new',
            'lead_source' => 'website',
        ]);

        // Seller replies
        $msg1 = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'inquiry_id' => $inquiry->id,
            'sender_id' => $this->seller->id,
            'message_type' => 'text',
            'body' => 'Selam! Yes, we can arrange a viewing on Thursday afternoon.',
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $msg1->id,
            'inquiry_id' => $inquiry->id,
            'sender_id' => $this->seller->id,
        ]);

        // Buyer attaches document / photo
        $msg2 = Message::query()->create([
            'uuid' => (string) Str::uuid(),
            'inquiry_id' => $inquiry->id,
            'sender_id' => $this->buyer->id,
            'message_type' => 'attachment',
            'body' => 'Attached proof of funds for offer negotiation.',
        ]);

        $att = MessageAttachment::query()->create([
            'message_id' => $msg2->id,
            'storage_disk' => 's3_private',
            'storage_path' => 'inquiries/attachments/proof_of_funds.pdf',
            'original_filename' => 'proof_of_funds.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024 * 512,
        ]);

        $this->assertDatabaseHas('message_attachments', [
            'id' => $att->id,
            'message_id' => $msg2->id,
            'original_filename' => 'proof_of_funds.pdf',
        ]);

        // Pipeline stage progression through SRS Section 73
        foreach ([
            InquiryStatus::Contacted,
            InquiryStatus::Viewing,
            InquiryStatus::Negotiating,
            InquiryStatus::Offer,
            InquiryStatus::Closed,
        ] as $stage) {
            $inquiry->update(['status' => $stage->value]);
            $this->assertSame($stage, $inquiry->fresh()->status);
        }
    }

    public function test_seller_phone_privacy_guard_enforces_strict_access_control(): void
    {
        $action = new RevealSellerPhoneAction();

        // 1. Seller has phone privacy mode MessagingOnly -> Access denied
        $this->seller->update(['phone_privacy' => PhonePrivacyMode::MessagingOnly]);
        $this->listing->update(['phone_privacy_override' => null]);

        $this->expectException(AccessDeniedHttpException::class);
        $action->execute($this->listing, $this->seller, $this->buyer, '127.0.0.1', 'Mozilla/5.0');
    }

    public function test_listing_override_blocks_phone_reveal_even_if_seller_allows(): void
    {
        $action = new RevealSellerPhoneAction();

        // Seller allows phone display, but listing overrides to 'hide_phone'
        $this->seller->update(['phone_privacy' => PhonePrivacyMode::ShowPhone]);
        $this->listing->update(['phone_privacy_override' => 'hide_phone']);

        $this->expectException(AccessDeniedHttpException::class);
        $action->execute($this->listing, $this->seller, $this->buyer, '127.0.0.1', 'Mozilla/5.0');
    }

    public function test_seller_phone_reveal_logs_audit_trail_and_enforces_rate_limiting(): void
    {
        RateLimiter::clear('phone_reveal:'.$this->buyer->id);

        $action = new RevealSellerPhoneAction();

        $this->seller->update(['phone_privacy' => PhonePrivacyMode::ShowPhone]);
        $this->listing->update(['phone_privacy_override' => null]);

        // Execute phone reveal
        $revealedPhone = $action->execute(
            $this->listing,
            $this->seller,
            $this->buyer,
            '196.188.10.45',
            'EthioTelecom-Browser'
        );

        $this->assertEquals('+251911234567', $revealedPhone);

        // Assert audit log record
        $this->assertDatabaseHas('phone_reveal_logs', [
            'listing_id' => $this->listing->id,
            'seller_id' => $this->seller->id,
            'viewer_user_id' => $this->buyer->id,
            'ip_address' => '196.188.10.45',
        ]);

        // Fill rate limit to threshold (15 attempts)
        for ($i = 0; $i < 14; $i++) {
            RateLimiter::hit('phone_reveal:'.$this->buyer->id, 3600);
        }

        // 16th attempt throws ValidationException
        $this->expectException(ValidationException::class);
        $action->execute($this->listing, $this->seller, $this->buyer, '196.188.10.45');
    }

    public function test_daily_market_analytics_aggregation_command_creates_snapshots(): void
    {
        $city = Location::query()->where('code', 'AA')->where('level', 'city')->firstOrFail();

        $this->property->update(['city_id' => $city->id]);

        $this->artisan('analytics:aggregate-daily')
            ->assertSuccessful();

        $this->assertDatabaseHas('market_analytics_snapshots', [
            'transaction_mode' => 'BUY',
            'city_id' => $city->id,
            'data_basis' => 'platform_asking_price_indicator',
        ]);

        $this->assertDatabaseHas('market_analytics_snapshots', [
            'transaction_mode' => 'BUY',
            'city_id' => null, // National
            'data_basis' => 'platform_asking_price_indicator',
        ]);

        $notice = MarketAnalyticsCalculator::disclosureNotice();
        $this->assertStringContainsString('Platform-Derived Market Indicator', $notice);
        $this->assertStringContainsString('SRS Sections 37 & 127', $notice);
    }

    public function test_inbox_and_admin_dashboard_routes(): void
    {
        // Guests redirected to login
        $this->get('/inbox')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');

        // Authenticated users get 200 OK
        $this->actingAs($this->buyer);

        $responseInbox = $this->get('/inbox');
        $responseInbox->assertStatus(200);
        $responseInbox->assertSee('Inquiries');
        $responseInbox->assertSee('INQ-2026-0000482');

        $responseAdmin = $this->get('/admin/dashboard');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('National Admin & Market Intelligence Center', false);
        $responseAdmin->assertSee('platform_asking_price_indicator');
        $responseAdmin->assertSee('Addis Ababa');
    }
}
