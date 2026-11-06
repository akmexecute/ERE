<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Developer\Actions\CalculateProjectConstructionProgressAction;
use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\ConstructionMilestone;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\PaymentPlanInstallment;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Location\Models\Location;
use App\Domains\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        protected CalculateProjectConstructionProgressAction $progressCalculator = new CalculateProjectConstructionProgressAction()
    ) {
    }

    /**
     * Display public development project showcase and unit stacking matrix explorer.
     */
    public function show(string $slug): View
    {
        $project = DevelopmentProject::with([
            'developer.company',
            'developer.user',
            'subcity',
            'city',
            'milestones',
            'paymentPlans.installments',
            'paymentAccounts',
            'buildings.floors.units',
        ])->where('slug', $slug)
          ->orWhere('project_code', $slug)
          ->first();

        if (! $project) {
            $project = $this->seedDemoProjectIfNeeded($slug);
        }

        // Calculate authoritative overall construction progress from saved milestones
        if ($project->milestones->isNotEmpty()) {
            $milestonesArray = $project->milestones->map(fn ($m) => [
                'stage_code' => $m->stage_code,
                'name_en' => $m->name_en,
                'progress_weight' => (float) $m->progress_weight,
                'completion_percentage' => (float) $m->completion_percentage,
            ])->toArray();

            try {
                $progressData = $this->progressCalculator->execute($milestonesArray);
                $overallProgress = $progressData['overall_progress_pct'];
            } catch (\Throwable) {
                $overallProgress = (float) $project->overall_progress_pct;
            }
        } else {
            $overallProgress = (float) $project->overall_progress_pct;
        }

        $paymentAccounts = $project->paymentAccounts->where('is_active', true);
        if ($paymentAccounts->isEmpty() && $project->developer) {
            $paymentAccounts = $project->developer->paymentAccounts->where('is_active', true);
        }

        return view('projects.show', [
            'project' => $project,
            'overallProgress' => $overallProgress,
            'defaultPlan' => $project->defaultPaymentPlan(),
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    /**
     * Lists development projects.
     */
    public function index(Request $request): View
    {
        $projects = DevelopmentProject::with(['developer', 'subcity', 'city'])
            ->latest()
            ->paginate(12);

        return view('projects.index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Display developer project creation form.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Authentication required to create a development project.');
        }

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $developerProfile = DeveloperProfile::with('company')->where('user_id', $user->id)->first();
        if (! $developerProfile && ! $isSuperAdmin) {
            $developerCompany = $user->companies()->where('company_type', 'developer')->first();
            if ($developerCompany) {
                $developerProfile = DeveloperProfile::firstOrCreate(
                    ['company_id' => $developerCompany->id],
                    [
                        'user_id' => $user->id,
                        'developer_code' => 'DEV-ETH-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                        'license_number' => 'DEV/LIC/' . date('Y') . '/' . mt_rand(1000, 9999),
                        'establishment_year' => 2020,
                        'delivered_projects_count' => 1,
                        'delivered_units_count' => 20,
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                    ]
                );
            }
        }

        if (! $isSuperAdmin && ! $developerProfile) {
            abort(403, 'Unauthorized. Only licensed developers or administrators may create development projects.');
        }

        $cities = Location::where('level', 'city')->orderBy('name_en')->get();
        $subcities = Location::where('level', 'subcity')->orderBy('name_en')->get();
        $companies = $isSuperAdmin
            ? \App\Domains\Company\Models\Company::where('company_type', 'developer')->get()
            : ($developerProfile?->company ? collect([$developerProfile->company]) : collect());

        return view('projects.create', [
            'developerProfile' => $developerProfile,
            'cities' => $cities,
            'subcities' => $subcities,
            'companies' => $companies,
            'projectTypes' => [
                'residential_complex' => 'Residential Complex',
                'mixed_use' => 'Mixed-Use Development',
                'commercial_tower' => 'Commercial Tower',
                'villa_community' => 'Gated Villa Community',
            ],
            'statuses' => [
                'planning' => 'Planning Phase',
                'pre_construction' => 'Pre-Construction',
                'under_construction' => 'Under Construction',
                'near_completion' => 'Near Completion',
                'completed' => 'Completed',
            ],
        ]);
    }

    /**
     * Store newly created development project.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Authentication required to create a development project.');
        }

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $developerProfile = DeveloperProfile::where('user_id', $user->id)->first();
        if (! $developerProfile && ! $isSuperAdmin) {
            $developerCompany = $user->companies()->where('company_type', 'developer')->first();
            if ($developerCompany) {
                $developerProfile = DeveloperProfile::firstOrCreate(
                    ['company_id' => $developerCompany->id],
                    [
                        'user_id' => $user->id,
                        'developer_code' => 'DEV-ETH-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                        'license_number' => 'DEV/LIC/' . date('Y') . '/' . mt_rand(1000, 9999),
                        'establishment_year' => 2020,
                        'delivered_projects_count' => 1,
                        'delivered_units_count' => 20,
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                    ]
                );
            }
        }

        if (! $isSuperAdmin && ! $developerProfile) {
            abort(403, 'Unauthorized to create development projects.');
        }

        $validated = $request->validate([
            'title_en' => 'required|string|max:180',
            'title_am' => 'nullable|string|max:180',
            'description_en' => 'required|string',
            'company_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'subcity_id' => 'nullable|integer',
            'address_line' => 'required|string|max:255',
            'project_type' => 'required|string|in:residential_complex,mixed_use,commercial_tower,villa_community',
            'status' => 'required|string|in:planning,pre_construction,under_construction,near_completion,completed',
            'land_area_sqm' => 'nullable|numeric|min:1',
            'building_permit_number' => 'nullable|string|max:80',
            'expected_completion_date' => 'nullable|date',
            'overall_progress_pct' => 'nullable|numeric|between:0,100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $companyId = $validated['company_id'] ?? $developerProfile?->company_id;
        if (! $companyId) {
            $companyId = DB::table('companies')->where('owner_user_id', $user->id)->value('id')
                ?? DB::table('companies')->value('id');
        }

        $devId = $developerProfile?->id ?? DeveloperProfile::where('company_id', $companyId)->value('id');
        if (! $devId) {
            $dev = DeveloperProfile::firstOrCreate(
                ['company_id' => $companyId],
                [
                    'user_id' => $user->id,
                    'developer_code' => 'DEV-ETH-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    'license_number' => 'DEV/LIC/' . date('Y') . '/' . mt_rand(1000, 9999),
                    'establishment_year' => 2020,
                    'delivered_projects_count' => 1,
                    'delivered_units_count' => 20,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]
            );
            $devId = $dev->id;
        }

        // Generate clean unique slug
        $baseSlug = \Illuminate\Support\Str::slug($validated['title_en']);
        $slug = $baseSlug;
        $counter = 1;
        while (DevelopmentProject::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $projectCode = 'PRJ-ETH-' . date('Y') . '-' . str_pad((string) mt_rand(100, 9999), 4, '0', STR_PAD_LEFT);

        $project = DB::transaction(function () use ($validated, $devId, $companyId, $slug, $projectCode) {
            $proj = DevelopmentProject::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'developer_id' => $devId,
                'company_id' => $companyId,
                'project_code' => $projectCode,
                'slug' => $slug,
                'title_en' => $validated['title_en'],
                'title_am' => $validated['title_am'] ?? null,
                'description_en' => $validated['description_en'],
                'project_type' => $validated['project_type'],
                'status' => $validated['status'],
                'subcity_id' => $validated['subcity_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'address_line' => $validated['address_line'],
                'latitude' => $validated['latitude'] ?? 9.0100,
                'longitude' => $validated['longitude'] ?? 38.7800,
                'land_area_sqm' => $validated['land_area_sqm'] ?? 2500.00,
                'building_permit_number' => $validated['building_permit_number'] ?? 'BP-' . date('Y') . '-' . mt_rand(1000, 9999),
                'expected_completion_date' => $validated['expected_completion_date'] ?? '2028-12-31',
                'overall_progress_pct' => $validated['overall_progress_pct'] ?? 10.00,
                'facilities_json' => [
                    'Underground Secure Parking',
                    'High-Speed Elevators',
                    'Backup Standby Generator',
                    'Central Water Reservoir',
                    '24/7 CCTV & Security',
                ],
            ]);

            // Default Building
            $building = ProjectBuilding::create([
                'project_id' => $proj->id,
                'building_code' => 'BLDG-1',
                'name_en' => 'Main Tower',
                'floors_count' => 10,
                'basement_floors_count' => 2,
            ]);

            // Default Floor 1
            BuildingFloor::create([
                'building_id' => $building->id,
                'project_id' => $proj->id,
                'floor_number' => 1,
                'floor_label' => 'Floor 1',
                'units_count' => 4,
            ]);

            // Construction Milestones
            $milestones = [
                ['stage_code' => 'foundation', 'name_en' => 'Foundation & Earthworks', 'name_am' => 'መሰረት እና ቁፋሮ', 'order_index' => 1, 'progress_weight' => 20.0, 'completion_percentage' => 50.0, 'is_verified' => true],
                ['stage_code' => 'structure', 'name_en' => 'Reinforced Concrete Structure', 'name_am' => 'ኮንክሪት መዋቅር', 'order_index' => 2, 'progress_weight' => 25.0, 'completion_percentage' => 0.0, 'is_verified' => false],
                ['stage_code' => 'masonry', 'name_en' => 'Blockwork & External Masonry', 'name_am' => 'የግድግዳ ግንባታ', 'order_index' => 3, 'progress_weight' => 15.0, 'completion_percentage' => 0.0, 'is_verified' => false],
                ['stage_code' => 'finishing', 'name_en' => 'Internal Finishing & MEP', 'name_am' => 'የውስጥ አጨራረስ', 'order_index' => 4, 'progress_weight' => 40.0, 'completion_percentage' => 0.0, 'is_verified' => false],
            ];
            foreach ($milestones as $m) {
                ConstructionMilestone::create(array_merge($m, ['project_id' => $proj->id]));
            }

            return $proj;
        });

        return redirect()->route('projects.manage', $project->slug)
            ->with('success', "Development project '{$project->title_en}' created successfully.");
    }

    /**
     * Developer project management dashboard: construction stages, payment plans, accounts & contracts.
     */
    public function manage(Request $request, string $slug): View|RedirectResponse
    {
        $project = DevelopmentProject::with([
            'developer.company',
            'developer.paymentAccounts',
            'milestones.logs.updater',
            'paymentPlans.installments',
            'paymentAccounts',
            'buildings.floors.units',
        ])->where('slug', $slug)
          ->orWhere('project_code', $slug)
          ->first();

        if (! $project) {
            $project = $this->seedDemoProjectIfNeeded($slug);
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Authentication is required to access developer project management.');
        }

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $isOwner = (int) $project->developer->company->owner_user_id === (int) $user->id;
        $isDevUser = (int) $project->developer->user_id === (int) $user->id;

        if (! $isSuperAdmin && ! $isOwner && ! $isDevUser) {
            abort(403, 'Unauthorized to manage this development project.');
        }

        $contracts = \App\Domains\Developer\Models\DeveloperSalesContract::with(['buyer', 'installments', 'amendments', 'transactions.paymentAccount'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        return view('projects.manage', [
            'project' => $project,
            'contracts' => $contracts,
            'paymentAccounts' => $project->paymentAccounts,
        ]);
    }

    /**
     * Store new unit in project inventory.
     */
    public function storeUnit(Request $request, string $slug): RedirectResponse
    {
        $project = DevelopmentProject::with(['developer.company', 'buildings.floors.units'])
            ->where('slug', $slug)
            ->orWhere('project_code', $slug)
            ->firstOrFail();

        $user = $request->user();
        if (! $user) {
            abort(403, 'Authentication required to manage project units.');
        }

        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $isOwner = (int) $project->developer->company->owner_user_id === (int) $user->id;
        $isDevUser = (int) $project->developer->user_id === (int) $user->id;

        if (! $isSuperAdmin && ! $isOwner && ! $isDevUser) {
            abort(403, 'Unauthorized to add units to this development project.');
        }

        $validated = $request->validate([
            'unit_number' => 'required|string|max:32',
            'unit_type' => 'required|string|in:studio,1br,2br,3br,4br,penthouse,commercial',
            'floor_number' => 'required|integer|min:0|max:100',
            'area_sqm' => 'required|numeric|min:10',
            'bedrooms' => 'required|integer|min:0|max:20',
            'bathrooms' => 'required|integer|min:0|max:20',
            'price_etb' => 'required|numeric|min:1',
            'orientation' => 'nullable|string|max:32',
            'parking_allocation' => 'nullable|integer|min:0|max:10',
            'status' => 'required|string|in:available,reserved,contracted,sold,blocked',
        ]);

        $building = $project->buildings->first();
        if (! $building) {
            $building = ProjectBuilding::create([
                'project_id' => $project->id,
                'building_code' => 'BLDG-1',
                'name_en' => 'Main Tower',
                'floors_count' => 10,
                'basement_floors_count' => 2,
            ]);
        }

        $floor = BuildingFloor::firstOrCreate(
            ['building_id' => $building->id, 'floor_number' => $validated['floor_number']],
            [
                'project_id' => $project->id,
                'floor_label' => "Floor {$validated['floor_number']}",
                'units_count' => 4,
            ]
        );

        $price = (float) $validated['price_etb'];
        $area = (float) $validated['area_sqm'];
        $pricePerSqm = round($price / max(1, $area), 2);

        $unit = ProjectUnit::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => $validated['unit_number'],
            'floor_number' => $validated['floor_number'],
            'unit_type' => $validated['unit_type'],
            'area_sqm' => $area,
            'bedrooms' => $validated['bedrooms'],
            'bathrooms' => $validated['bathrooms'],
            'price_etb' => $price,
            'price_per_sqm_etb' => $pricePerSqm,
            'orientation' => $validated['orientation'] ?? 'north_east',
            'parking_allocation' => $validated['parking_allocation'] ?? 1,
            'status' => $validated['status'],
        ]);

        return redirect()->route('projects.manage', ['slug' => $project->slug, 'tab' => 'units'])
            ->with('success', "Unit {$unit->unit_number} added successfully to {$project->title_en}.");
    }

    /**
     * Seeds an authoritative database demonstration project if database is clean.
     */
    private function seedDemoProjectIfNeeded(string $requestedSlug): DevelopmentProject
    {
        return DB::transaction(function () use ($requestedSlug): DevelopmentProject {
            // Find or create authoritative developer user
            $user = User::query()->firstOrCreate(
                ['phone' => '+251911234567'],
                [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'name' => 'Bole Horizon Developments Officer',
                    'status' => 'active',
                    'phone_verified_at' => now(),
                    'national_id_number' => 'FAN-2026-DEV-0012',
                    'identity_verified_at' => now(),
                    'verification_level' => 3,
                ]
            );

            // Find or create company
            $companyId = DB::table('companies')->where('owner_user_id', $user->id)->value('id');

            if (! $companyId) {
                $companyId = DB::table('companies')->insertGetId([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'owner_user_id' => $user->id,
                    'legal_name' => 'Bole Horizon Developments PLC',
                    'trade_name' => 'Horizon Towers',
                    'company_type' => 'developer',
                    'verification_level' => 3,
                    'status' => 'verified',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Developer Profile
            $developer = DeveloperProfile::firstOrCreate(
                ['company_id' => $companyId],
                [
                    'user_id' => $user->id,
                    'developer_code' => 'DEV-ETH-000042',
                    'license_number' => 'DEV/LIC/2024/0981',
                    'establishment_year' => 2012,
                    'delivered_projects_count' => 6,
                    'delivered_units_count' => 450,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'bio_en' => 'Premier high-rise residential developer headquartered in Addis Ababa.',
                ]
            );

            $subcityId = Location::where('level', 'subcity')->value('id');
            $cityId = Location::where('level', 'city')->value('id');

            // Development Project
            $project = DevelopmentProject::create([
                'developer_id' => $developer->id,
                'company_id' => $companyId,
                'project_code' => 'PRJ-ETH-2026-0012',
                'slug' => $requestedSlug ?: 'bole-tower-residences',
                'title_en' => 'Bole Horizon Luxury Residences',
                'title_am' => 'ቦሌ ሆራይዘን የቅንጦት መኖሪያዎች',
                'description_en' => 'Masterplanned twin-tower residential development in the heart of Bole Medhanialem. Built to International Eurocode 8 seismic standards with panoramic city skyline views, continuous backup generators, and fiber-optic backbone.',
                'project_type' => 'residential_complex',
                'status' => ProjectStatus::UnderConstruction,
                'subcity_id' => $subcityId,
                'city_id' => $cityId,
                'address_line' => 'Bole Medhanialem, Behind Edna Mall, Addis Ababa',
                'latitude' => 9.0012300,
                'longitude' => 38.7845600,
                'land_area_sqm' => 4500.00,
                'building_permit_number' => 'AA/CON/BP-2025-001429',
                'expected_completion_date' => '2027-12-31',
                'overall_progress_pct' => 58.75,
                'facilities_json' => [
                    'Underground Secure Parking',
                    'Dual High-Speed Schindler Lifts',
                    '250kVA Standby Cummins Generator',
                    'Rooftop Infinity Terrace & Gym',
                    'Central Water Reservoir (50,000L)',
                    '24/7 CCTV & Security Command Center',
                ],
            ]);

            // Construction Milestones (SRS Section 90 example: 58.75% progress)
            $milestones = [
                ['stage_code' => 'foundation', 'name_en' => 'Foundation & Earthworks', 'name_am' => 'መሰረት እና ቁፋሮ', 'order_index' => 1, 'progress_weight' => 20.0, 'completion_percentage' => 100.0, 'is_verified' => true],
                ['stage_code' => 'structure', 'name_en' => 'Reinforced Concrete Structure', 'name_am' => 'ኮንክሪት መዋቅር', 'order_index' => 2, 'progress_weight' => 25.0, 'completion_percentage' => 85.0, 'is_verified' => true],
                ['stage_code' => 'masonry', 'name_en' => 'Blockwork & External Masonry', 'name_am' => 'የግድግዳ ግንባታ', 'order_index' => 3, 'progress_weight' => 15.0, 'completion_percentage' => 60.0, 'is_verified' => true],
                ['stage_code' => 'roofing', 'name_en' => 'Roofing & Waterproofing', 'name_am' => 'ጣሪያ እና እርጥበት መከላከያ', 'order_index' => 4, 'progress_weight' => 10.0, 'completion_percentage' => 40.0, 'is_verified' => false],
                ['stage_code' => 'finishing', 'name_en' => 'Internal Finishing & MEP', 'name_am' => 'የውስጥ አጨራረስ', 'order_index' => 5, 'progress_weight' => 20.0, 'completion_percentage' => 15.0, 'is_verified' => false],
                ['stage_code' => 'handover', 'name_en' => 'Final Inspection & Handover', 'name_am' => 'ርክክብ', 'order_index' => 6, 'progress_weight' => 10.0, 'completion_percentage' => 0.0, 'is_verified' => false],
            ];

            foreach ($milestones as $m) {
                ConstructionMilestone::create(array_merge($m, ['project_id' => $project->id]));
            }

            // Developer Payment Plan (SRS Section 91: 100.00% total)
            $plan = DeveloperPaymentPlan::create([
                'project_id' => $project->id,
                'name_en' => 'Standard Milestone Construction Plan (100%)',
                'name_am' => 'መደበኛ የግንባታ ምዕራፍ የክፍያ እቅድ',
                'is_default' => true,
                'is_active' => true,
                'version' => 1,
            ]);

            $planInstallments = [
                ['sequence_number' => 1, 'milestone_label_en' => 'Reservation Deposit', 'percentage' => 10.00, 'trigger_milestone_code' => null, 'due_days_after_trigger' => 0],
                ['sequence_number' => 2, 'milestone_label_en' => 'Foundation Stage Completion', 'percentage' => 20.00, 'trigger_milestone_code' => 'foundation', 'due_days_after_trigger' => 15],
                ['sequence_number' => 3, 'milestone_label_en' => 'Structure Top-Out', 'percentage' => 20.00, 'trigger_milestone_code' => 'structure', 'due_days_after_trigger' => 15],
                ['sequence_number' => 4, 'milestone_label_en' => 'Roofing & Waterproofing', 'percentage' => 20.00, 'trigger_milestone_code' => 'roofing', 'due_days_after_trigger' => 15],
                ['sequence_number' => 5, 'milestone_label_en' => 'Finishing & Glazing', 'percentage' => 20.00, 'trigger_milestone_code' => 'finishing', 'due_days_after_trigger' => 15],
                ['sequence_number' => 6, 'milestone_label_en' => 'Key Handover & Title Deed', 'percentage' => 10.00, 'trigger_milestone_code' => 'handover', 'due_days_after_trigger' => 30],
            ];

            foreach ($planInstallments as $pi) {
                PaymentPlanInstallment::create(array_merge($pi, ['payment_plan_id' => $plan->id]));
            }

            // Developer Manual Payment Destination Accounts (SRS Section 91 & Option B)
            \App\Domains\Developer\Models\DeveloperPaymentAccount::firstOrCreate(
                ['account_number' => '1000123456789'],
                [
                    'company_id' => $companyId,
                    'developer_id' => $developer->id,
                    'project_id' => $project->id,
                    'account_label' => 'CBE Developer Payment Account',
                    'institution_name' => 'Commercial Bank of Ethiopia',
                    'account_holder_name' => 'Bole Horizon Developments PLC',
                    'branch_name' => 'Bole Medhanialem Branch',
                    'swift_code' => 'CBETETAA',
                    'currency' => 'ETB',
                    'transfer_instructions' => 'Include Unit Number and Reservation Code in transaction narration.',
                    'is_active' => true,
                ]
            );

            // Buildings (Tower A & Tower B)
            $buildingA = ProjectBuilding::create([
                'project_id' => $project->id,
                'building_code' => 'TOWER-A',
                'name_en' => 'Tower A (Horizon West)',
                'floors_count' => 8,
                'basement_floors_count' => 2,
            ]);

            $buildingB = ProjectBuilding::create([
                'project_id' => $project->id,
                'building_code' => 'TOWER-B',
                'name_en' => 'Tower B (Horizon East)',
                'floors_count' => 8,
                'basement_floors_count' => 2,
            ]);

            // Create Floors and Units for Tower A
            for ($f = 1; $f <= 5; $f++) {
                $floor = BuildingFloor::create([
                    'building_id' => $buildingA->id,
                    'project_id' => $project->id,
                    'floor_number' => $f,
                    'floor_label' => "Floor {$f}",
                    'units_count' => 4,
                ]);

                // Create 4 units per floor with diverse inventory statuses
                $unitConfigs = [
                    ['suffix' => '01', 'type' => '1br', 'area' => 75.0, 'beds' => 1, 'baths' => 1, 'price' => 9750000.0, 'status' => UnitInventoryStatus::Available],
                    ['suffix' => '02', 'type' => '2br', 'area' => 115.0, 'beds' => 2, 'baths' => 2, 'price' => 14950000.0, 'status' => $f === 2 ? UnitInventoryStatus::Reserved : UnitInventoryStatus::Available],
                    ['suffix' => '03', 'type' => '3br', 'area' => 165.0, 'beds' => 3, 'baths' => 3, 'price' => 21450000.0, 'status' => $f === 3 ? UnitInventoryStatus::Contracted : UnitInventoryStatus::Available],
                    ['suffix' => '04', 'type' => 'penthouse', 'area' => 240.0, 'beds' => 4, 'baths' => 4, 'price' => 33600000.0, 'status' => $f === 1 ? UnitInventoryStatus::Sold : UnitInventoryStatus::Available],
                ];

                foreach ($unitConfigs as $cfg) {
                    ProjectUnit::create([
                        'project_id' => $project->id,
                        'building_id' => $buildingA->id,
                        'floor_id' => $floor->id,
                        'unit_number' => "A-{$f}{$cfg['suffix']}",
                        'floor_number' => $f,
                        'unit_type' => $cfg['type'],
                        'area_sqm' => $cfg['area'],
                        'bedrooms' => $cfg['beds'],
                        'bathrooms' => $cfg['baths'],
                        'price_etb' => $cfg['price'],
                        'price_per_sqm_etb' => round($cfg['price'] / $cfg['area'], 2),
                        'orientation' => $cfg['suffix'] === '01' ? 'north_east' : 'south_west',
                        'parking_allocation' => $cfg['type'] === 'penthouse' ? 2 : 1,
                        'status' => $cfg['status'],
                    ]);
                }
            }

            return $project->load([
                'developer.user',
                'subcity',
                'city',
                'milestones',
                'paymentPlans.installments',
                'buildings.floors.units',
            ]);
        });
    }
}
