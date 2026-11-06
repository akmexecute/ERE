<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Services\AuditService;
use App\Domains\Administration\Services\SettingService;
use App\Domains\Identity\Actions\SendOtpAction;
use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Domains\Identity\Services\ArraySmsGateway;
use App\Domains\Location\Models\Location;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

final class FoundationAuthenticationAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ArraySmsGateway::reset();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    // ==========================================
    // 1. SYSTEM SETTINGS TESTS (Section 4.1)
    // ==========================================

    public function test_system_settings_type_validation_and_public_private_access(): void
    {
        /** @var SettingService $settings */
        $settings = app(SettingService::class);

        // Register public setting
        $settings->register(
            group: 'general',
            key: 'site_name',
            defaultValue: 'EthioProperty National',
            type: 'string',
            isPublic: true,
            description: 'Public platform title'
        );

        // Register private setting (e.g. payout threshold or internal config)
        $settings->register(
            group: 'finance',
            key: 'max_daily_payout_etb',
            defaultValue: 5000000,
            type: 'int',
            isPublic: false,
            description: 'Internal daily payout limit'
        );

        // Public retrieval works
        $this->assertSame('EthioProperty National', $settings->get('site_name'));

        // Private setting cannot be accessed without explicit privilege flag
        $this->assertNull($settings->get('max_daily_payout_etb', allowPrivate: false));
        $this->assertSame(5000000, $settings->get('max_daily_payout_etb', allowPrivate: true));

        // Setting update with type validation
        $settings->set('site_name', 'EthioProperty Enterprise');
        $this->assertSame('EthioProperty Enterprise', $settings->get('site_name'));

        // Setting update with invalid type throws
        $this->expectException(InvalidArgumentException::class);
        $settings->set('max_daily_payout_etb', 'invalid_string_not_int');
    }

    // ==========================================
    // 2. REAL OTP WORKFLOW & KEYED-HMAC (Section 6.2 & 6.3)
    // ==========================================

    public function test_sends_otp_via_configured_sms_gateway_and_stores_keyed_hmac(): void
    {
        /** @var SendOtpAction $sendOtp */
        $sendOtp = app(SendOtpAction::class);

        $phone = '0911223344';
        $sendOtp->execute($phone, purpose: 'login', ipAddress: '127.0.0.1');

        // Verify sent via ArraySmsGateway
        $sent = ArraySmsGateway::getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('+251911223344', $sent[0]['phone']);
        $this->assertSame(6, strlen($sent[0]['code']));

        // Verify database record has keyed-HMAC hash, NOT plaintext or unsalted hash
        $otpRecord = DB::table('otp_codes')->where('phone', '+251911223344')->first();
        $this->assertNotNull($otpRecord);
        $this->assertNotSame($sent[0]['code'], $otpRecord->code_hash);
        $this->assertSame(hash_hmac('sha256', $sent[0]['code'], (string) config('app.key')), $otpRecord->code_hash);
    }

    public function test_verifies_otp_and_authenticates_atomically_preventing_replay(): void
    {
        /** @var SendOtpAction $sendOtp */
        $sendOtp = app(SendOtpAction::class);
        /** @var VerifyOtpAndAuthenticateAction $verifyOtp */
        $verifyOtp = app(VerifyOtpAndAuthenticateAction::class);

        $phone = '0922334455';
        $sendOtp->execute($phone, purpose: 'login');
        $code = ArraySmsGateway::getLastCodeFor('+251922334455');

        // First verification succeeds
        $user = $verifyOtp->execute(
            rawPhone: $phone,
            code: $code,
            purpose: 'login',
            name: 'Kassahun Belay',
            initialRole: 'buyer'
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('+251922334455', $user->phone);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->hasRole('buyer'));

        // Challenge is marked consumed
        $otpRecord = DB::table('otp_codes')->where('phone', '+251922334455')->first();
        $this->assertNotNull($otpRecord->consumed_at);

        // Attempting to reuse the same OTP code fails
        $this->expectException(ValidationException::class);
        $verifyOtp->execute(
            rawPhone: $phone,
            code: $code,
            purpose: 'login'
        );
    }

    // ==========================================
    // 3. SAFE ROLE ONBOARDING (Section 6.5)
    // ==========================================

    public function test_prevents_privilege_escalation_to_admin_or_officer_roles(): void
    {
        /** @var SendOtpAction $sendOtp */
        $sendOtp = app(SendOtpAction::class);
        /** @var VerifyOtpAndAuthenticateAction $verifyOtp */
        $verifyOtp = app(VerifyOtpAndAuthenticateAction::class);

        $phone = '0933445566';
        $sendOtp->execute($phone);
        $code = ArraySmsGateway::getLastCodeFor('+251933445566');

        // Attacker attempts to register with role = super_admin or verification_officer
        $user = $verifyOtp->execute(
            rawPhone: $phone,
            code: $code,
            initialRole: 'super_admin'
        );

        // Must NOT have super_admin or staff privileges; must be sanitized to default buyer
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertTrue($user->hasRole('buyer'));
    }

    // ==========================================
    // 4. SUSPENDED USER ACCESS DENIAL (Section 6.7)
    // ==========================================

    public function test_suspended_users_are_strictly_denied_permissions(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Suspended Administrator',
            'phone' => '+251911009988',
            'status' => 'suspended',
        ]);
        $admin->assignRoleBySlug('super_admin');

        // Even though user has super_admin role, being suspended denies all permissions!
        $this->assertFalse($admin->hasPermission('property.delete'));
        $this->assertFalse($admin->hasPermission('verification.approve'));
    }

    // ==========================================
    // 5. RBAC SEEDED PERMISSION MATRIX (Section 6.7)
    // ==========================================

    public function test_seeded_roles_possess_explicit_permissions(): void
    {
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Almaz',
            'phone' => '+251911440011',
            'status' => 'active',
        ]);
        $officer->assignRoleBySlug('verification_officer');

        $this->assertTrue($officer->hasPermission('verification.review'));
        $this->assertTrue($officer->hasPermission('verification.approve'));
        $this->assertTrue($officer->hasPermission('verification.reject'));
        $this->assertFalse($officer->hasPermission('property.delete'));

        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Customer Abebe',
            'phone' => '+251911440022',
            'status' => 'active',
        ]);
        $buyer->assignRoleBySlug('buyer');

        $this->assertTrue($buyer->hasPermission('property.view'));
        $this->assertTrue($buyer->hasPermission('booking.create'));
        $this->assertFalse($buyer->hasPermission('property.delete'));
        $this->assertFalse($buyer->hasPermission('verification.approve'));
    }

    // ==========================================
    // 6. AUDIT IMMUTABILITY & REDACTION (Section 5)
    // ==========================================

    public function test_audit_logs_are_append_only_and_redact_sensitive_data(): void
    {
        /** @var AuditService $audit */
        $audit = app(AuditService::class);

        $log = $audit->log(
            action: 'user.registered',
            auditable: 'User',
            oldValues: null,
            newValues: [
                'name' => 'Taye',
                'phone' => '+251911776655',
                'password' => 'plaintext_secret_must_be_redacted',
            ],
            reason: 'Self-service registration'
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('+251911776655', $log->new_values['phone']);

        // Eloquent level deletion is blocked
        $this->expectException(LogicException::class);
        $log->delete();
    }

    // ==========================================
    // 7. LOCATION & GIS ENDPOINTS (Section 7 & 8)
    // ==========================================

    public function test_location_children_and_reverse_geocode_endpoints(): void
    {
        $this->seed(EthiopianLocationsSeeder::class);

        $ethiopiaId = Location::where('level', 'country')->value('id');
        $this->assertNotNull($ethiopiaId);

        // Test API: GET /api/v1/locations/children
        $response = $this->getJson("/api/v1/locations/children?parent_id={$ethiopiaId}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'code', 'name', 'level']]]);

        // Test API: GET /api/v1/gis/reverse-geocode at Bole coordinates (8.9894, 38.7890)
        $geoResponse = $this->getJson('/api/v1/gis/reverse-geocode?lat=8.9894&lng=38.7890');
        $geoResponse->assertStatus(200);
        $geoData = $geoResponse->json('data');
        $this->assertNotEmpty($geoData);
        $this->assertArrayHasKey('subcity', $geoData);
        $this->assertSame('BOLE', $geoData['subcity']['code']);

        // Test API: Faraway coordinate (> 15km, e.g. Indian Ocean 0, 50) returns empty match
        $farResponse = $this->getJson('/api/v1/gis/reverse-geocode?lat=0.0&lng=50.0');
        $farResponse->assertStatus(200);
        $this->assertEmpty($farResponse->json('data'));
    }
}
