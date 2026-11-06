<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmDashboardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_dashboard_page_loads_successfully_for_guest(): void
    {
        $response = $this->get('/crm/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Offer Negotiation Room');
        $response->assertSee('CRM Lead Pipeline');
        $response->assertSee('Commission Calculator');
        $response->assertSee('OFR-2026-000089');
    }

    public function test_crm_dashboard_page_loads_successfully_for_authenticated_domain_user(): void
    {
        $this->seed(RbacRolePermissionSeeder::class);

        /** @var User $user */
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Agent Abebe Bikila',
            'email' => 'abebe@example.com',
            'phone' => '+251911223344',
            'status' => 'active',
            'verification_level' => 2,
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-0001',
            'identity_verified_at' => now(),
        ]);
        $user->assignRole('agent');

        $response = $this->actingAs($user)->get('/crm/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Agent Abebe Bikila');
        $response->assertSee('Offer Negotiation Room');
        $response->assertSee('CRM Lead Pipeline');
    }
}
