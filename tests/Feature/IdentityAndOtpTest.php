<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Actions\SendOtpAction;
use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IdentityAndOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    public function test_sends_otp_and_hashes_code_in_database(): void
    {
        $action = new SendOtpAction();
        $code = $action->execute('0911234567', 'login', '127.0.0.1');

        $this->assertSame(6, strlen($code));
        $this->assertDatabaseHas('otp_codes', [
            'phone' => '+251911234567',
            'code_hash' => hash('sha256', $code),
            'purpose' => 'login',
        ]);
    }

    public function test_send_otp_enforces_rate_limiting(): void
    {
        $action = new SendOtpAction();
        $phone = '0922334455';

        $action->execute($phone, 'login', '10.0.0.1');
        $action->execute($phone, 'login', '10.0.0.1');
        $action->execute($phone, 'login', '10.0.0.1');

        $this->expectException(ValidationException::class);
        $action->execute($phone, 'login', '10.0.0.1');
    }

    public function test_verifies_otp_and_authenticates_new_user_with_role(): void
    {
        $sendAction = new SendOtpAction();
        $plainCode = $sendAction->execute('0711998877', 'login', '127.0.0.1');

        $verifyAction = new VerifyOtpAndAuthenticateAction();
        $user = $verifyAction->execute(
            rawPhone: '0711998877',
            code: $plainCode,
            name: 'Kenenisa Bekele',
            initialRole: 'property_owner',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0 TestBrowser'
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('+251711998877', $user->phone);
        $this->assertSame('Kenenisa Bekele', $user->name);
        $this->assertSame(1, $user->verification_level);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue($user->hasRole('property_owner'));

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'phone_or_email' => '+251711998877',
            'succeeded' => true,
        ]);
    }

    public function test_rejects_incorrect_otp_code(): void
    {
        $sendAction = new SendOtpAction();
        $sendAction->execute('0988776655', 'login', '127.0.0.1');

        $verifyAction = new VerifyOtpAndAuthenticateAction();

        $this->expectException(ValidationException::class);
        $verifyAction->execute(
            rawPhone: '0988776655',
            code: '000000',
            initialRole: 'buyer'
        );
    }

    public function test_http_otp_endpoints_flow(): void
    {
        $sendRes = $this->postJson('/auth/otp/send', [
            'phone' => '0933445566',
        ]);

        $sendRes->assertOk();
        $sendRes->assertJsonStructure(['success', 'message', 'debug_code']);

        $code = $sendRes->json('debug_code');

        $verifyRes = $this->postJson('/auth/otp/verify', [
            'phone' => '0933445566',
            'code' => $code,
            'role' => 'agent',
            'name' => 'Derartu Tulu',
        ]);

        $verifyRes->assertOk();
        $this->assertAuthenticated();
    }
}
