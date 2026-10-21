<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    public function test_assigns_and_verifies_roles(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Tesfaye',
            'phone' => '+251911445566',
            'status' => 'active',
        ]);

        $this->assertFalse($user->hasRole('agent'));

        $user->assignRoleBySlug('agent');
        $this->assertTrue($user->hasRole('agent'));

        $user->assignRoleBySlug('developer');
        $this->assertTrue($user->hasRole('developer'));
    }

    public function test_super_admin_has_all_permissions_automatically(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'phone' => '+251911999000',
            'status' => 'active',
        ]);

        $admin->assignRoleBySlug('super_admin');

        $this->assertTrue($admin->hasPermission('property.delete'));
        $this->assertTrue($admin->hasPermission('verification.approve'));
        $this->assertTrue($admin->hasPermission('payment.reconcile'));
    }

    public function test_verifies_granular_role_and_direct_permissions(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Daniel',
            'phone' => '+251911888777',
            'status' => 'active',
        ]);

        $user->assignRoleBySlug('verification_officer');

        // Attach permission to role
        $roleId = DB::table('roles')->where('slug', 'verification_officer')->value('id');
        $permId = DB::table('permissions')->where('slug', 'verification.review')->value('id');
        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permId,
        ]);

        $this->assertTrue($user->hasPermission('verification.review'));
        $this->assertFalse($user->hasPermission('property.delete'));

        // Attach direct permission
        $delPermId = DB::table('permissions')->where('slug', 'property.delete')->value('id');
        DB::table('user_permissions')->insertOrIgnore([
            'user_id' => $user->id,
            'permission_id' => $delPermId,
        ]);

        $this->assertTrue($user->hasPermission('property.delete'));
    }
}
