<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Document\Actions\GenerateDocumentSignedUrlAction;
use App\Domains\Identity\Services\IdentityGateService;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase2Milestone2NationalIdAndIdentityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        Storage::fake('s3_private');
    }

    private function createUser(array $attributes = []): User
    {
        static $counter = 100000;
        $counter++;

        return User::query()->create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test User ' . $counter,
            'phone' => '+251911' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            'status' => 'active',
            'verification_level' => 1,
            'phone_verified_at' => now(),
        ], $attributes));
    }

    public function test_public_browsing_remains_open_without_forced_document_upload(): void
    {
        // Unauthenticated or unverified users can view public endpoints without being forced to upload documents
        $response = $this->get('/');
        $this->assertTrue(in_array($response->status(), [200, 302])); // 200 OK or home redirect

        $gateService = app(IdentityGateService::class);
        $this->assertFalse($gateService->requiresIdentityVerification('view_public_listing'));
        $this->assertFalse($gateService->requiresIdentityVerification('search_properties'));
    }

    public function test_unverified_phone_blocks_protected_actions(): void
    {
        $gateService = app(IdentityGateService::class);

        /** @var User $unverifiedPhoneUser */
        $unverifiedPhoneUser = $this->createUser([
            'phone_verified_at' => null,
            'identity_verified_at' => null,
            'verification_level' => 0,
        ]);

        $check = $gateService->check($unverifiedPhoneUser, 'book_stay');
        $this->assertFalse($check['is_allowed']);
        $this->assertSame('phone_verification', $check['missing_requirement']);
        $this->assertFalse($gateService->canPerformAction($unverifiedPhoneUser, 'book_stay'));
    }

    public function test_identity_gate_blocks_protected_actions_when_national_id_missing(): void
    {
        $gateService = app(IdentityGateService::class);

        /** @var User $user */
        $user = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
            'verification_level' => 1,
        ]);

        $protectedActions = [
            'book_stay',
            'make_offer',
            'rental_application',
            'sign_lease',
            'reserve_unit',
            'publish_listings',
        ];

        foreach ($protectedActions as $action) {
            $check = $gateService->check($user, $action);
            $this->assertFalse($check['is_allowed'], "Action {$action} should be blocked for unverified identity.");
            $this->assertSame('national_id_number', $check['missing_requirement']);
            $this->assertFalse($gateService->canPerformAction($user, $action));
        }
    }

    public function test_upload_front_and_back_identity_documents_and_submit_verification(): void
    {
        /** @var User $user */
        $user = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
        ]);

        $frontFile = UploadedFile::fake()->image('fayda_front.jpg', 600, 400)->size(1500);
        $backFile = UploadedFile::fake()->image('fayda_back.jpg', 600, 400)->size(1500);

        $response = $this->actingAs($user)->post('/identity/verify', [
            'national_id_number' => 'FAN-9876-5432-1098',
            'national_id_type' => 'fayda',
            'id_front_image' => $frontFile,
            'id_back_image' => $backFile,
            'redirect_url' => '/dashboard',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success');

        $user->refresh();

        // 1. User attributes updated
        $this->assertSame('FAN-9876-5432-1098', $user->national_id_number);
        $this->assertSame('fayda', $user->national_id_type);
        $this->assertNotNull($user->id_front_document_id);
        $this->assertNotNull($user->id_back_document_id);

        // 2. Document records created
        $this->assertDatabaseHas('documents', ['id' => $user->id_front_document_id]);
        $this->assertDatabaseHas('documents', ['id' => $user->id_back_document_id]);

        // 3. Masked ID attribute
        $this->assertSame('FAN-****-1098', $user->masked_national_id);

        // 4. Verification pending record created
        $this->assertDatabaseHas('verifications', [
            'requested_by_user_id' => $user->id,
            'verifiable_id' => $user->id,
            'status' => 'pending',
            'requested_level' => 1,
        ]);
    }

    public function test_rejects_invalid_file_mimes_and_oversized_files(): void
    {
        /** @var User $user */
        $user = $this->createUser();

        // Executable / invalid mime
        $exeFile = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');
        $validFile = UploadedFile::fake()->image('valid.png', 200, 200);

        $response = $this->actingAs($user)->post('/identity/verify', [
            'national_id_number' => 'FAN-1111-2222',
            'national_id_type' => 'fayda',
            'id_front_image' => $exeFile,
            'id_back_image' => $validFile,
        ]);

        $response->assertSessionHasErrors(['id_front_image']);

        // Oversized file (>10MB)
        $oversizedFile = UploadedFile::fake()->image('huge.jpg')->size(15000); // 15MB
        $response2 = $this->actingAs($user)->post('/identity/verify', [
            'national_id_number' => 'FAN-1111-2222',
            'national_id_type' => 'fayda',
            'id_front_image' => $oversizedFile,
            'id_back_image' => $validFile,
        ]);

        $response2->assertSessionHasErrors(['id_front_image']);
    }

    public function test_privacy_protection_allows_uploader_and_staff_but_blocks_counterparties(): void
    {
        /** @var User $uploader */
        $uploader = $this->createUser();

        /** @var User $staffUser */
        $staffUser = $this->createUser();
        $staffUser->assignRole('platform_admin');

        /** @var User $counterpartyUser */
        $counterpartyUser = $this->createUser();

        $frontFile = UploadedFile::fake()->image('front.png', 400, 300)->size(500);
        $backFile = UploadedFile::fake()->image('back.png', 400, 300)->size(500);

        $this->actingAs($uploader)->post('/identity/verify', [
            'national_id_number' => 'FAN-3333-4444-5555',
            'national_id_type' => 'fayda',
            'id_front_image' => $frontFile,
            'id_back_image' => $backFile,
        ]);

        $uploader->refresh();
        $frontDocument = $uploader->idFrontDocument;
        $this->assertNotNull($frontDocument);

        $signedUrlAction = app(GenerateDocumentSignedUrlAction::class);

        // 1. Original uploader can access signed URL
        $uploaderAccess = $signedUrlAction->execute($uploader, $frontDocument);
        $this->assertNotEmpty($uploaderAccess['url']);

        // 2. Staff user can access signed URL
        $staffAccess = $signedUrlAction->execute($staffUser, $frontDocument);
        $this->assertNotEmpty($staffAccess['url']);

        // 3. Counterparty (stranger / guest / host) is strictly blocked
        $this->expectException(AuthorizationException::class);
        $signedUrlAction->execute($counterpartyUser, $frontDocument);
    }

    public function test_verified_user_passes_all_protected_action_gates(): void
    {
        $gateService = app(IdentityGateService::class);

        /** @var User $verifiedUser */
        $verifiedUser = $this->createUser([
            'national_id_number' => 'FAN-7777-8888-9999',
            'national_id_type' => 'fayda_digital_qr',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        $frontDoc = \App\Domains\Document\Models\Document::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'documentable_type' => 'user',
            'documentable_id' => $verifiedUser->id,
            'uploaded_by' => $verifiedUser->id,
            'document_type_code' => 'national_id',
            'title' => 'Front ID',
            'access_level' => 'verification_only',
            'current_version_number' => 1,
        ]);

        $verifiedUser->update([
            'id_front_document_id' => $frontDoc->id,
        ]);

        $protectedActions = [
            'book_stay',
            'make_offer',
            'rental_application',
            'sign_lease',
            'reserve_unit',
            'publish_listings',
        ];

        foreach ($protectedActions as $action) {
            $check = $gateService->check($verifiedUser, $action);
            $this->assertTrue($check['is_allowed'], "Verified user must be allowed for {$action}");
            $this->assertNull($check['missing_requirement']);
            $this->assertTrue($gateService->canPerformAction($verifiedUser, $action));
        }
    }

    public function test_http_route_blocks_unverified_user_from_making_purchase_offer(): void
    {
        /** @var User $unverifiedUser */
        $unverifiedUser = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
        ]);

        /** @var User $verifiedUser */
        $verifiedUser = $this->createUser([
            'national_id_number' => 'FAN-1234-5678-9012',
            'national_id_type' => 'fayda_digital_qr',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);
        $frontDoc = \App\Domains\Document\Models\Document::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'documentable_type' => 'user',
            'documentable_id' => $verifiedUser->id,
            'uploaded_by' => $verifiedUser->id,
            'document_type_code' => 'national_id',
            'title' => 'Front ID',
            'access_level' => 'verification_only',
            'current_version_number' => 1,
        ]);
        $verifiedUser->update(['id_front_document_id' => $frontDoc->id]);

        $listing = \App\Models\Listing::factory()->create(['price_etb' => 25_000_000]);

        // Unverified user is blocked with 403 Forbidden
        $response = $this->actingAs($unverifiedUser)->postJson("/api/v1/listings/{$listing->id}/negotiate", [
            'offered_amount' => 22_000_000,
        ]);
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Identity verification required.',
            'redirect' => route('identity.verify', ['action' => 'make_offer']),
        ]);

        // Verified user passes the gate
        $responseVerified = $this->actingAs($verifiedUser)->postJson("/api/v1/listings/{$listing->id}/negotiate", [
            'offered_amount' => 22_000_000,
        ]);
        $responseVerified->assertStatus(201);
    }

    public function test_http_route_blocks_unverified_user_from_reserving_unit(): void
    {
        /** @var User $unverifiedUser */
        $unverifiedUser = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedUser)->postJson('/api/v1/units/999/reserve', [
            'idempotency_key' => 'IDEM-TEST-HOLD-001',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Identity verification required.',
            'redirect' => route('identity.verify', ['action' => 'reserve_unit']),
        ]);
    }

    public function test_http_route_blocks_unverified_user_from_short_stay_hold(): void
    {
        /** @var User $unverifiedUser */
        $unverifiedUser = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedUser)->postJson('/api/stays/hold', [
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-05',
            'adults' => 2,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Identity verification required.',
            'redirect' => route('identity.verify', ['action' => 'book_stay']),
        ]);
    }

    public function test_http_route_blocks_unverified_user_from_rental_application_and_lease_signing(): void
    {
        /** @var User $unverifiedUser */
        $unverifiedUser = $this->createUser([
            'national_id_number' => null,
            'identity_verified_at' => null,
        ]);

        // 1. Rental Application redirects to identity.verify with action=rental_application and return redirect
        $responseApp = $this->actingAs($unverifiedUser)->post('/rental-applications', []);
        $responseApp->assertRedirect(route('identity.verify', [
            'action' => 'rental_application',
            'redirect' => url('/rental-applications'),
        ]));

        // 2. Sign Lease redirects to identity.verify with action=sign_lease and return redirect
        $leaseUuid = (string) \Illuminate\Support\Str::uuid();
        $responseSign = $this->actingAs($unverifiedUser)->post("/leases/{$leaseUuid}/sign", []);
        $responseSign->assertRedirect(route('identity.verify', [
            'action' => 'sign_lease',
            'redirect' => url("/leases/{$leaseUuid}/sign"),
        ]));
    }
}
