<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\CRM\Services\CrmLeadAccessGuard;
use App\Domains\Offer\Models\OfferNegotiationEvent;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Viewing\Models\ViewingAppointment;
use App\Services\CommissionCalculatorService;
use App\Services\CRM\PipelineVelocityEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __construct(
        protected PipelineVelocityEngine $velocityEngine,
        protected CommissionCalculatorService $commissionCalculator,
        protected ?CrmLeadAccessGuard $leadGuard = null
    ) {
        $this->leadGuard ??= new CrmLeadAccessGuard();
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $requestedBranch = $request->query('branch', 'ALL');

        // Resolve permitted branches for the selector
        $branchesQuery = CompanyBranch::query()->where('is_active', true)->orderBy('is_head_office', 'desc');

        $isAdmin = $user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin'));

        if ($user && ! $isAdmin) {
            $companyIds = Company::query()->where('owner_user_id', $user->id)->pluck('id')->all();
            $agentBranchId = $user->agentProfile?->branch_id;

            $branchesQuery->where(function ($q) use ($companyIds, $agentBranchId) {
                if (! empty($companyIds)) {
                    $q->whereIn('company_id', $companyIds);
                }
                if ($agentBranchId) {
                    $q->orWhere('id', $agentBranchId);
                }
            });
        }

        $branchRecords = $branchesQuery->get();

        $branchOptions = [];
        if ($isAdmin || ! $user || Company::query()->where('owner_user_id', $user?->id)->exists()) {
            $branchOptions[] = [
                'code' => 'ALL',
                'name' => 'National Overview (All Branches)',
                'city' => 'All Locations',
            ];
        }

        foreach ($branchRecords as $br) {
            $branchOptions[] = [
                'code' => $br->branch_code,
                'name' => $br->name_en . ($br->is_head_office ? ' (Head Office)' : ''),
                'city' => $br->city?->name_en ?? 'Ethiopia',
            ];
        }

        // Default baseline branches if DB is freshly seeded or empty
        if (empty($branchOptions)) {
            $branchOptions = [
                ['code' => 'ALL', 'name' => 'National Overview (All Branches)', 'city' => 'All Locations'],
                ['code' => 'HQ-AA', 'name' => 'Head Office — Addis Ababa (Bole Medhanialem)', 'city' => 'Addis Ababa'],
                ['code' => 'BR-ADD', 'name' => 'Addis Branch (CMC / Kazanchis)', 'city' => 'Addis Ababa'],
                ['code' => 'BR-HAR', 'name' => 'Harar Branch (ሐረር ሸንኮር / Ras Mekonnen Ave)', 'city' => 'Harar'],
                ['code' => 'BR-JIJ', 'name' => 'Jijiga Branch (ጅጅጋ Central Business District)', 'city' => 'Jijiga'],
                ['code' => 'BR-DIR', 'name' => 'Dire Dawa Branch (ድሬዳዋ Kebele 02 Commercial)', 'city' => 'Dire Dawa'],
            ];
        }

        $stats = $this->velocityEngine->computeDashboardStats($requestedBranch, $user);
        $activeBranchCode = $stats['active_branch_code'] ?? 'ALL';

        // Load real leads with user authorization scoping
        $leadQuery = CrmLead::query()->latest('created_at')->limit(20);
        if ($user) {
            $leadQuery = $this->leadGuard->scopeQueryForUser($user, $leadQuery);
        }
        if ($activeBranchCode !== 'ALL') {
            $leadQuery->whereHas('branch', fn ($q) => $q->where('branch_code', $activeBranchCode));
        }

        $leads = $leadQuery->get()->map(function (CrmLead $l): array {
            $branchCode = $l->branch_id ? CompanyBranch::query()->where('id', $l->branch_id)->value('branch_code') : 'HQ-AA';
            return [
                'code' => $l->lead_code,
                'client' => $l->client_name,
                'phone' => $l->client_phone,
                'branch' => $branchCode ?? 'HQ-AA',
                'source' => $l->lead_source,
                'stage' => $l->stage,
                'property' => $l->preferred_location_note ?: 'Addis Ababa Residential',
                'budget' => $l->budget_max_etb ? 'ETB ' . number_format((float) $l->budget_max_etb) : 'Flexible',
                'followUp' => $l->next_follow_up_at?->diffForHumans() ?? 'Pending Contact',
            ];
        })->all();

        // Load real viewings scoped to user
        $viewingQuery = ViewingAppointment::query()->latest('scheduled_at')->limit(10);
        if ($user && ! $isAdmin) {
            $viewingQuery->where(function ($q) use ($user) {
                $q->where('seller_or_agent_user_id', $user->id)
                    ->orWhere('agent_id', $user->id)
                    ->orWhere('buyer_user_id', $user->id);
            });
        }
        $viewings = $viewingQuery->get()->map(function (ViewingAppointment $v): array {
            return [
                'id' => $v->id,
                'property' => 'Listing #' . $v->listing_id,
                'client' => $v->buyer?->name ?? 'Verified Buyer',
                'agent' => $v->agent?->name ?? 'Assigned Agent',
                'timeSlot' => $v->scheduled_time_slot,
                'type' => $v->viewing_type,
                'status' => $v->status,
                'location' => 'In-Person Scheduled',
            ];
        })->all();

        // Load real negotiation offer and events scoped to user
        $offerQuery = PropertyOffer::query()->with('events')->latest('id');
        if ($user && ! $isAdmin) {
            $offerQuery->where(function ($q) use ($user) {
                $q->where('seller_user_id', $user->id)
                    ->orWhere('agent_user_id', $user->id)
                    ->orWhere('buyer_user_id', $user->id);
            });
        }
        $offer = $offerQuery->first();
        $negotiationHistory = [];
        $askingPriceEtb = 25_000_000.0;
        $offerStatus = 'countered';
        $awaitingParty = 'buyer';

        if ($offer) {
            $askingPriceEtb = (float) $offer->listed_asking_price_etb;
            $offerStatus = $offer->status;
            $awaitingParty = $offer->awaiting_response_from ?? 'closed';

            $negotiationHistory = $offer->events->map(function (OfferNegotiationEvent $e): array {
                return [
                    'round' => (int) $e->round_number,
                    'party' => $e->actor_party,
                    'label' => $e->actor_party === 'buyer' ? 'Buyer Offer' : 'Seller Counteroffer',
                    'amount' => (float) $e->proposed_amount_etb,
                    'time' => $e->created_at?->diffForHumans() ?? 'Recently',
                    'note' => $e->message ?? '',
                ];
            })->all();
        }

        return view('crm.dashboard', [
            'stats' => $stats,
            'selectedBranch' => $activeBranchCode,
            'branches' => $branchOptions,
            'leads' => $leads,
            'viewings' => $viewings,
            'negotiationHistory' => $negotiationHistory,
            'askingPriceEtb' => $askingPriceEtb,
            'offerStatus' => $offerStatus,
            'awaitingParty' => $awaitingParty,
        ]);
    }

    public function calculateCommission(Request $request): JsonResponse
    {
        $salePrice = (float) $request->input('sale_price', 20000000.0);
        $companyId = (int) $request->input('company_id', 1);

        $result = $this->commissionCalculator->calculate($salePrice, $companyId);

        return response()->json($result);
    }
}
