<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Actions\SendOtpAction;
use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Domains\Identity\Services\ArraySmsGateway;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Localization\Models\SystemTranslation;
use App\Domains\Localization\Services\ExcelTranslationService;
use App\Domains\Localization\Services\TranslationManager;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

final class Milestone1VerificationAndExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
    }

    private function createAdminWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => "Admin {$roleSlug}",
            'phone' => '+251911' . rand(100000, 999999),
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 2,
        ]);
        $user->assignRoleBySlug($roleSlug);

        return $user;
    }

    public function test_property_admin_cannot_access_security_center(): void
    {
        $propertyAdmin = $this->createAdminWithRole('property_admin');

        $response = $this->actingAs($propertyAdmin)->get('/admin/security');
        $response->assertStatus(403);

        $targetUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Target User',
            'phone' => '+251911223344',
            'status' => 'active',
        ]);

        $postResponse = $this->actingAs($propertyAdmin)->post("/admin/security/users/{$targetUser->id}/status", [
            'status' => 'suspended',
            'reason' => 'Unauthorized attempt by property_admin',
        ]);
        $postResponse->assertStatus(403);
    }

    public function test_property_admin_cannot_access_or_manage_languages(): void
    {
        $propertyAdmin = $this->createAdminWithRole('property_admin');

        $this->actingAs($propertyAdmin)->get('/admin/languages')->assertStatus(403);
        $this->actingAs($propertyAdmin)->get('/admin/languages/export')->assertStatus(403);
        $this->actingAs($propertyAdmin)->post('/admin/languages/import-preview', [])->assertStatus(403);
    }

    public function test_property_admin_cannot_deactivate_locations(): void
    {
        $propertyAdmin = $this->createAdminWithRole('property_admin');

        $location = Location::query()->create([
            'parent_id' => null,
            'level' => 'city',
            'code' => 'TEST_CITY',
            'slug' => 'test-city',
            'name_en' => 'Test City',
            'is_active' => true,
        ]);

        $this->actingAs($propertyAdmin)
            ->post("/admin/locations/{$location->id}/deactivate")
            ->assertStatus(403);
    }

    public function test_platform_admin_can_access_security_and_languages(): void
    {
        $platformAdmin = $this->createAdminWithRole('platform_admin');

        $this->actingAs($platformAdmin)->get('/admin/security')->assertStatus(200);
        $this->actingAs($platformAdmin)->get('/admin/languages')->assertStatus(200);
        $this->actingAs($platformAdmin)->get('/admin/locations')->assertStatus(200);
    }

    public function test_authenticated_suspended_user_is_immediately_logged_out_by_middleware(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Active To Suspended',
            'phone' => '+251911887766',
            'status' => 'active',
        ]);

        // Access while active works
        $this->actingAs($user)->get('/dashboard')->assertStatus(200);

        // Suspend user in database
        $user->status = 'suspended';
        $user->save();

        // Next request immediately invalidates session, logs out, and redirects to login
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspicious_login_detection_for_repeated_failures(): void
    {
        $phone = '+251911999888';
        $ip = '197.156.100.50';
        $gateway = new ArraySmsGateway();
        $sendAction = new SendOtpAction($gateway);
        $verifyAction = app(VerifyOtpAndAuthenticateAction::class);

        $sendAction->execute($phone, 'auth', $ip);

        // Perform 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            try {
                $verifyAction->execute($phone, '000000', $ip, 'TestAgent');
            } catch (\Exception) {
                // Expected invalid code
            }
        }

        // 4th attempt should be flagged as suspicious brute-force
        try {
            $verifyAction->execute($phone, '000000', $ip, 'TestAgent');
        } catch (\Exception) {
            // Expected invalid code
        }

        $latestHistory = DB::table('login_histories')
            ->where('phone_or_email', $phone)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($latestHistory);
        $this->assertTrue((bool) $latestHistory->is_suspicious);
    }

    public function test_location_root_resolution_works_when_ethiopia_id_is_not_1(): void
    {
        DB::table('locations')->delete();

        // Create dummy prior records so Ethiopia gets an ID other than 1
        $dummy1 = Location::query()->create([
            'parent_id' => null,
            'level' => 'city',
            'code' => 'DUMMY1',
            'slug' => 'dummy1',
            'name_en' => 'Dummy 1',
            'is_active' => true,
        ]);

        $ethiopia = Location::query()->create([
            'parent_id' => null,
            'level' => 'country',
            'code' => 'ETH',
            'slug' => 'ethiopia',
            'name_en' => 'Ethiopia',
            'name_am' => 'ኢትዮጵያ',
            'name_om' => 'Itoophiyaa',
            'is_active' => true,
        ]);

        $this->assertNotEquals(1, $ethiopia->id);

        $resolved = Location::getRootCountry();
        $this->assertNotNull($resolved);
        $this->assertEquals($ethiopia->id, $resolved->id);

        $region = Location::query()->create([
            'parent_id' => $ethiopia->id,
            'level' => 'region',
            'code' => 'OROMIA',
            'slug' => 'oromia',
            'name_en' => 'Oromia',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/locations/children?root=1');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Oromia', $data[0]['name']);
    }

    public function test_inactive_ancestor_prevents_descendant_locations_from_public_selection(): void
    {
        $country = Location::query()->create([
            'parent_id' => null,
            'level' => 'country',
            'code' => 'ETH',
            'slug' => 'ethiopia',
            'name_en' => 'Ethiopia',
            'is_active' => true,
        ]);

        $inactiveRegion = Location::query()->create([
            'parent_id' => $country->id,
            'level' => 'region',
            'code' => 'INACTIVE_REG',
            'slug' => 'inactive-region',
            'name_en' => 'Inactive Region',
            'ancestry_path' => "/{$country->id}/",
            'is_active' => false, // Deactivated!
        ]);

        $zone = Location::query()->create([
            'parent_id' => $inactiveRegion->id,
            'level' => 'zone',
            'code' => 'ZONE_SUB',
            'slug' => 'zone-sub',
            'name_en' => 'Zone Under Inactive Region',
            'ancestry_path' => "/{$country->id}/{$inactiveRegion->id}/",
            'is_active' => true,
        ]);

        // Requesting children of inactiveRegion directly should return empty list
        $response = $this->getJson("/api/v1/locations/children?parent_id={$inactiveRegion->id}");
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));

        // Requesting children of zone (whose ancestor is inactive) should also return empty list
        $response2 = $this->getJson("/api/v1/locations/children?parent_id={$zone->id}");
        $response2->assertStatus(200);
        $this->assertEmpty($response2->json('data'));
    }

    public function test_draft_or_inactive_properties_do_not_leak_in_map_search(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Seller',
            'phone' => '+251911445566',
            'status' => 'active',
        ]);

        $draftProp = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'title_en' => 'Draft Unapproved Luxury Villa',
            'property_code' => 'PROP-DRAFT',
            'category_id' => 1,
            'property_type_id' => 1,
            'latitude' => 9.0100,
            'longitude' => 38.7600,
            'status' => PropertyStatus::Draft->value, // Draft!
        ]);

        $listingTypeId = DB::table('listing_types')->value('id') ?? 1;

        Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-DRAFT-01',
            'listing_type_id' => $listingTypeId,
            'property_id' => $draftProp->id,
            'seller_user_id' => $owner->id,
            'published_by_user_id' => $owner->id,
            'slug' => 'draft-unapproved-luxury-villa',
            'transaction_mode' => 'BUY',
            'price_etb' => 20000000,
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        $response = $this->get('/search?' . http_build_query([
            'ne_lat' => 9.0500,
            'ne_lng' => 38.8000,
            'sw_lat' => 8.9500,
            'sw_lng' => 38.7000,
        ]));

        $response->assertStatus(200);
        $response->assertDontSee('Draft Unapproved Luxury Villa');
    }

    public function test_excel_export_generates_valid_workbook_with_unicode_and_formula_defense(): void
    {
        $admin = $this->createAdminWithRole('platform_admin');

        // Create a test phrase with Amharic, Afaan Oromo, and a formula-like string
        SystemTranslation::query()->create([
            'group' => 'property',
            'key' => 'type.car',
            'locale' => 'en',
            'value' => 'Car',
            'is_reviewed' => true,
        ]);
        SystemTranslation::query()->create([
            'group' => 'property',
            'key' => 'type.car',
            'locale' => 'am',
            'value' => 'መኪና',
            'is_reviewed' => true,
        ]);
        SystemTranslation::query()->create([
            'group' => 'property',
            'key' => 'type.car',
            'locale' => 'om',
            'value' => 'Konkolaataa',
            'is_reviewed' => true,
        ]);

        // Formula injection test string
        SystemTranslation::query()->create([
            'group' => 'security',
            'key' => 'formula_test',
            'locale' => 'en',
            'value' => '=SUM(A1:A10)',
            'is_reviewed' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/languages/export');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Verify the binary stream is a valid zip archive
        $content = $response->streamedContent();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_exp_') . '.xlsx';
        file_put_contents($tempFile, $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempFile));

        // Check Translations sheet exists
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotEmpty($sheetXml);

        // Check Unicode preservation (Amharic መኪና)
        $this->assertStringContainsString('መኪና', $sheetXml);
        $this->assertStringContainsString('Konkolaataa', $sheetXml);

        // Check formula defense: prepended with single quote '=SUM
        $this->assertStringContainsString("'=SUM(A1:A10)", $sheetXml);

        $zip->close();
        @unlink($tempFile);
    }

    public function test_excel_import_preview_and_confirm_persists_translations_safely(): void
    {
        $admin = $this->createAdminWithRole('platform_admin');

        // Prepare an Excel workbook using ExcelTranslationService export as source
        $excelService = app(ExcelTranslationService::class);
        $export = $excelService->exportTranslations();
        $uploadedFile = new UploadedFile($export['path'], 'translations.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // Preview step
        $previewResp = $this->actingAs($admin)->postJson('/admin/languages/import-preview', [
            'file' => $uploadedFile,
        ]);

        $previewResp->assertStatus(200);
        $previewResp->assertJsonStructure([
            'is_valid',
            'total_rows',
            'new_count',
            'update_count',
            'preview_token',
        ]);

        $previewToken = $previewResp->json('preview_token');
        $this->assertNotEmpty($previewToken);

        // Confirm step
        $confirmResp = $this->actingAs($admin)->postJson('/admin/languages/import-confirm', [
            'preview_token' => $previewToken,
            'clear_empty_cells' => false,
        ]);

        $confirmResp->assertStatus(200);
        $confirmResp->assertJson(['status' => 'success']);

        // Verify audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'translations.excel_imported',
            'user_id' => $admin->id,
        ]);
    }

    public function test_excel_import_rejects_invalid_file_extension(): void
    {
        $admin = $this->createAdminWithRole('platform_admin');

        $fakeFile = UploadedFile::fake()->create('malicious.csv', 100, 'text/csv');

        $response = $this->actingAs($admin)->postJson('/admin/languages/import-preview', [
            'file' => $fakeFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'is_valid' => false,
        ]);
    }

    public function test_unauthorized_user_cannot_export_or_import_translations(): void
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Buyer',
            'phone' => '+251911333222',
            'status' => 'active',
        ]);
        $buyer->assignRoleBySlug('buyer');

        $this->actingAs($buyer)->get('/admin/languages/export')->assertStatus(403);
        $this->actingAs($buyer)->postJson('/admin/languages/import-preview', [])->assertStatus(403);
        $this->actingAs($buyer)->postJson('/admin/languages/import-confirm', [])->assertStatus(403);
    }
}
