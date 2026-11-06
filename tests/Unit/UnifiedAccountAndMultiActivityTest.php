<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UnifiedAccountAndMultiActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    public function test_user_can_hold_multiple_customer_roles_simultaneously(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Multi-Persona',
            'phone' => '+251911112233',
            'status' => 'active',
            'verification_level' => 2,
        ]);

        // Assign multiple roles
        $user->assignRoleBySlug('buyer');
        $user->assignRoleBySlug('property_owner');
        $user->assignRoleBySlug('host');
        $user->assignRoleBySlug('tenant');

        $this->assertTrue($user->hasRole('buyer'));
        $this->assertTrue($user->hasRole('property_owner'));
        $this->assertTrue($user->hasRole('host'));
        $this->assertTrue($user->hasRole('tenant'));

        $this->assertTrue($user->hasAnyRole(['host', 'agent']));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_staff_roles_cannot_be_self_assigned_via_otp_authentication(): void
    {
        $phone = '+251911445566';
        $code = '123456';

        DB::table('otp_codes')->insert([
            'phone' => $phone,
            'purpose' => 'login',
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = new VerifyOtpAndAuthenticateAction();

        // Attempt privilege escalation to super_admin or platform_admin
        $user = $action->execute(
            rawPhone: '0911445566',
            code: $code,
            initialRole: 'super_admin'
        );

        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertTrue($user->hasRole('buyer'));
    }

    public function test_multi_activity_relationships_resolve_on_user_model(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Host and Landlord',
            'phone' => '+251922334455',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->properties());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->listings());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->hostProfile());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->tenantProfile());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->agentProfile());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $user->developerProfile());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->guestBookings());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->leasesAsTenant());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->leasesAsLandlord());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->rentalApplicationsAsTenant());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->rentalApplicationsAsLandlord());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->companies());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->unitReservations());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->offersAsBuyer());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $user->offersAsSeller());
    }

    public function test_user_activity_helpers_reflect_actual_state(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Activity Check User',
            'phone' => '+251933445566',
            'status' => 'active',
        ]);

        $this->assertFalse($user->isHost());
        $this->assertFalse($user->isTenant());
        $this->assertFalse($user->isLandlord());
        $this->assertFalse($user->isStaff());

        // Assign staff role
        $user->assignRoleBySlug('platform_admin');
        $this->assertTrue($user->isStaff());
    }
}
