<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\DeveloperPaymentAccount;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\PaymentPlanInstallment;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectShowViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_page_loads_with_matrix_and_payment_accounts(): void
    {
        $this->seed(RbacRolePermissionSeeder::class);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Developer User',
            'phone' => '+251911990011',
            'status' => 'active',
            'verification_level' => 3,
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'legal_name' => 'Bole Horizon Developers PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dev = DeveloperProfile::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'developer_code' => 'DEV-ETH-000088',
            'verification_status' => 'verified',
        ]);

        $project = DevelopmentProject::query()->create([
            'developer_id' => $dev->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-TEST-VIEW',
            'slug' => 'horizon-bole-towers',
            'title_en' => 'Horizon Bole Luxury Towers',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
            'overall_progress_pct' => 58.75,
            'land_area_sqm' => 3500.00,
        ]);

        $plan = DeveloperPaymentPlan::query()->create([
            'project_id' => $project->id,
            'name_en' => 'Milestone Plan (100%)',
            'is_default' => true,
        ]);

        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $plan->id,
            'sequence_number' => 1,
            'milestone_label_en' => 'Deposit',
            'percentage' => 10.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $plan->id,
            'sequence_number' => 2,
            'milestone_label_en' => 'Handover',
            'percentage' => 90.0,
        ]);

        $building = ProjectBuilding::query()->create([
            'project_id' => $project->id,
            'building_code' => 'TOWER-1',
            'name_en' => 'Tower One',
        ]);

        $floor = BuildingFloor::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_number' => 1,
            'floor_label' => 'Floor 1',
        ]);

        ProjectUnit::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => '101',
            'floor_number' => 1,
            'unit_type' => '2br',
            'area_sqm' => 100.0,
            'price_etb' => 10_000_000.0,
            'status' => UnitInventoryStatus::Available,
        ]);

        DeveloperPaymentAccount::query()->create([
            'company_id' => $companyId,
            'developer_id' => $dev->id,
            'project_id' => $project->id,
            'account_label' => 'Commercial Bank of Ethiopia Developer Account',
            'institution_name' => 'Commercial Bank of Ethiopia',
            'account_holder_name' => 'Bole Horizon Developers PLC',
            'account_number' => '100011223344',
            'is_active' => true,
        ]);

        $response = $this->get('/projects/horizon-bole-towers');

        $response->assertStatus(200);
        $response->assertSee('Horizon Bole Luxury Towers');
        $response->assertSee('Unit Stacking Matrix');
        $response->assertSee('Master Site Plan Blueprint');
        $response->assertSee('Architectural 3D Render');
        $response->assertSee('100011223344');
        $response->assertSee('58.75%');
    }

    public function test_developer_project_management_dashboard_loads(): void
    {
        $this->seed(RbacRolePermissionSeeder::class);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Lead Developer',
            'phone' => '+251911990022',
            'status' => 'active',
            'verification_level' => 3,
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'legal_name' => 'Metropolitan Developments PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dev = DeveloperProfile::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'developer_code' => 'DEV-ETH-000099',
            'verification_status' => 'verified',
        ]);

        $project = DevelopmentProject::query()->create([
            'developer_id' => $dev->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-TEST-MGT',
            'slug' => 'metropolitan-central-tower',
            'title_en' => 'Metropolitan Central Tower',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
            'overall_progress_pct' => 45.00,
        ]);

        $response = $this->actingAs($user)->get("/projects/{$project->slug}/manage");

        $response->assertStatus(200);
        $response->assertSee('Developer Operations');
        $response->assertSee('Metropolitan Central Tower');
        $response->assertSee('Configurable Construction Stages');
        $response->assertSee('Create Custom Payment Plan');
        $response->assertSee('Developer Manual Payment Accounts');
    }
}

