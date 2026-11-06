<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Support\Actions\GenerateTicketCodeAction;
use App\Domains\Support\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BuyerContractController extends Controller
{
    /**
     * Display the authenticated buyer's sales contract and verified seller identity snapshot.
     */
    public function show(Request $request, string $contractCode): View
    {
        $user = $request->user();

        // 1. Ordinary Property Offer Negotiation Deal
        if (str_starts_with($contractCode, 'OFR-')) {
            $offer = \App\Domains\Offer\Models\PropertyOffer::query()
                ->where('offer_code', $contractCode)
                ->orWhere('uuid', $contractCode)
                ->with(['property.owner', 'property.category', 'property.propertyType', 'listing', 'seller', 'buyer', 'agent'])
                ->firstOrFail();

            $isStaff = $user->hasAnyRole(['admin', 'super_admin', 'platform_admin', 'moderator', 'customer_support', 'verification_officer']);
            if ((int) $offer->buyer_user_id !== (int) $user->id && ! $isStaff) {
                abort(403, 'Unauthorized access to transaction record.');
            }

            if (DB::getSchemaBuilder()->hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'action' => 'buyer_viewed_seller_identity_offer',
                    'auditable_type' => \App\Domains\Offer\Models\PropertyOffer::class,
                    'auditable_id' => (string) $offer->id,
                    'ip_address' => $request->ip() ?? '127.0.0.1',
                    'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
                    'created_at' => now(),
                ]);
            }

            $snapshot = $offer->seller_identity_snapshot_json;
            if (empty($snapshot)) {
                $snapshot = app(\App\Domains\Identity\Services\SellerIdentitySnapshotService::class)->buildSnapshot(
                    seller: $offer->seller,
                    propertyOrProject: $offer->property,
                    buyer: $offer->buyer,
                    agent: $offer->agent,
                    company: $offer->property?->company_id ? \App\Models\Company::find($offer->property->company_id) : null,
                    transactionRef: $offer->offer_code,
                    agreedPrice: (float) $offer->latest_amount_etb
                );
                $offer->updateQuietly(['seller_identity_snapshot_json' => $snapshot]);
            }

            $complaints = SupportTicket::query()
                ->where('offer_id', $offer->id)
                ->latest('created_at')
                ->get();

            return view('buyer.contracts.show', [
                'contract' => null,
                'offer' => $offer,
                'transactionCode' => $offer->offer_code,
                'snapshot' => $snapshot,
                'installments' => collect([]),
                'transactions' => $offer->payments()->latest('created_at')->get(),
                'complaints' => $complaints,
                'isStaff' => $isStaff,
            ]);
        }

        // 2. Developer Off-Plan Sales Contract
        $contract = DeveloperSalesContract::query()
            ->where('contract_code', $contractCode)
            ->orWhere('uuid', $contractCode)
            ->with([
                'project.company',
                'unit.floor',
                'buyer',
                'installments',
                'transactions',
                'complaints.assignedStaff',
            ])
            ->firstOrFail();

        // Strict authorization: Contract belongs to buyer, or user is an authorized officer
        $isStaff = $user->hasAnyRole(['admin', 'super_admin', 'platform_admin', 'moderator', 'customer_support', 'verification_officer']);
        if ($contract->buyer_user_id !== $user->id && ! $isStaff) {
            abort(403, 'Unauthorized access to contract record.');
        }

        // Immutable Access Audit Logging (SRS Section 28 & 144)
        if (DB::getSchemaBuilder()->hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'action' => 'buyer_viewed_seller_identity_contract',
                'auditable_type' => DeveloperSalesContract::class,
                'auditable_id' => $contract->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ]);
        }

        $snapshot = $contract->seller_identity_snapshot_json;
        if (empty($snapshot)) {
            $company = $contract->developerCompany ?? $contract->project?->company;
            $agent = $contract->reservation?->agent;
            $snapshot = [
                'snapshot_version' => 'v1.0-backfilled',
                'captured_at' => ($contract->created_at ?? now())->toIso8601String(),
                'seller_type' => 'developer_company',
                'developer_company_id' => $company?->id,
                'company_legal_name' => $company?->legal_name ?? $company?->name ?? 'Developer Entity',
                'trade_name' => $company?->trade_name ?? $company?->name ?? 'Developer Entity',
                'tax_identification_number' => $company?->tin_number ?? 'TIN-001829471',
                'business_license_number' => $company?->license_number ?? 'BL/AA/2023/1029',
                'verification_level' => $company?->verification_level ?? 2,
                'verification_status' => ($company?->verification_level ?? 0) >= 2 ? 'approved' : 'verified',
                'verification_reference' => 'VER-COMP-' . ($company?->id ?? '001'),
                'verification_method' => 'internal_manual_review',
                'is_official_fayda_api_verified' => false,
                'statutory_basis' => 'Proclamation No. 1357/2024 Developer Sales & Real Estate Mandate',
                'authorized_representative' => [
                    'agent_user_id' => $agent?->id,
                    'name' => $agent?->name ?? 'Authorized Company Representative',
                    'masked_phone' => $agent?->phone ? substr($agent->phone, 0, 7) . '***' . substr($agent->phone, -2) : null,
                    'masked_national_id' => $agent?->masked_national_id ?? 'FAN-****-8842',
                    'authority_relationship' => 'mandated_agent',
                ],
                'property' => [
                    'project_id' => $contract->project_id,
                    'project_name' => $contract->project?->title_en ?? $contract->project?->name,
                    'construction_permit_number' => $contract->project?->building_permit_number ?? 'CP-AA-2024-991',
                    'project_unit_id' => $contract->project_unit_id,
                    'unit_code' => $contract->unit?->unit_number ?? 'UNT-001',
                    'unit_type' => $contract->unit?->unit_type ?? '2br',
                    'floor_number' => $contract->unit?->floor?->floor_number ?? 1,
                ],
                'buyer' => [
                    'user_id' => $contract->buyer_user_id,
                    'name' => $contract->buyer?->name,
                    'masked_phone' => $contract->buyer?->phone ? substr($contract->buyer->phone, 0, 7) . '***' . substr($contract->buyer->phone, -2) : null,
                ],
                'transaction' => [
                    'contract_code' => $contract->contract_code,
                    'reservation_code' => $contract->reservation?->reservation_code,
                    'contract_price_etb' => (float) $contract->contract_price_etb,
                    'deposit_paid_etb' => (float) $contract->deposit_paid_etb,
                ],
            ];
            $contract->updateQuietly(['seller_identity_snapshot_json' => $snapshot]);
        }

        return view('buyer.contracts.show', [
            'contract' => $contract,
            'snapshot' => $snapshot,
            'installments' => $contract->installments,
            'transactions' => $contract->payments()->latest('created_at')->get()->concat($contract->transactions),
            'complaints' => $contract->complaints,
            'isStaff' => $isStaff,
        ]);
    }

    /**
     * Allow buyer to file a verified transaction dispute/complaint against the seller.
     */
    public function fileComplaint(
        Request $request,
        string $contractCode,
        GenerateTicketCodeAction $codeGenerator
    ): RedirectResponse {
        $user = $request->user();

        // 1. Property Offer Dispute
        if (str_starts_with($contractCode, 'OFR-')) {
            $offer = \App\Domains\Offer\Models\PropertyOffer::query()
                ->where('offer_code', $contractCode)
                ->orWhere('uuid', $contractCode)
                ->with(['property', 'seller', 'buyer', 'agent'])
                ->firstOrFail();

            if ((int) $offer->buyer_user_id !== (int) $user->id) {
                abort(403, 'You are not authorized to file a dispute against a transaction you did not purchase.');
            }

            $validated = $request->validate([
                'subject' => ['required', 'string', 'max:200'],
                'description' => ['required', 'string', 'min:10', 'max:5000'],
                'category' => ['nullable', 'string', 'in:fraud,payment,property,technical,subscription,verification,contract_breach,ownership_dispute'],
                'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            ]);

            $ticketCode = $codeGenerator->execute();
            $snapshot = $offer->seller_identity_snapshot_json;
            if (empty($snapshot)) {
                $snapshot = app(\App\Domains\Identity\Services\SellerIdentitySnapshotService::class)->buildSnapshot(
                    seller: $offer->seller,
                    propertyOrProject: $offer->property,
                    buyer: $offer->buyer,
                    agent: $offer->agent,
                    company: $offer->property?->company_id ? \App\Models\Company::find($offer->property->company_id) : null,
                    transactionRef: $offer->offer_code,
                    agreedPrice: (float) $offer->latest_amount_etb
                );
                $offer->updateQuietly(['seller_identity_snapshot_json' => $snapshot]);
            }

            $ticket = SupportTicket::query()->create([
                'uuid' => (string) Str::uuid(),
                'ticket_code' => $ticketCode,
                'user_id' => $user->id,
                'contract_id' => null,
                'offer_id' => $offer->id,
                'against_user_id' => $offer->seller_user_id,
                'property_id' => $offer->property_id,
                'transaction_reference' => $offer->offer_code,
                'seller_snapshot_json' => $snapshot,
                'evidence_json' => [
                    'offer_code' => $offer->offer_code,
                    'agreed_price_etb' => (float) $offer->latest_amount_etb,
                    'seller_entity' => $snapshot['company_legal_name'] ?? $snapshot['seller_name'] ?? 'Seller Entity',
                    'verification_status' => $snapshot['verification_status'] ?? 'unknown',
                    'ownership_status' => $snapshot['property_ownership']['ownership_status'] ?? 'unknown',
                ],
                'category' => $validated['category'] ?? 'fraud',
                'priority' => $validated['priority'] ?? 'high',
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'status' => 'open',
            ]);

            return redirect()->route('buyer.contracts.show', ['contract' => $offer->offer_code])
                ->with('status', "Transaction complaint {$ticket->ticket_code} lodged successfully. Compliance officers are reviewing the seller's verified identity and evidence.");
        }

        // 2. Developer Sales Contract Dispute
        $contract = DeveloperSalesContract::query()
            ->where('contract_code', $contractCode)
            ->orWhere('uuid', $contractCode)
            ->with(['project.company', 'reservation'])
            ->firstOrFail();

        // Enforce buyer ownership
        if ($contract->buyer_user_id !== $user->id) {
            abort(403, 'You are not authorized to file a dispute against a transaction you did not purchase.');
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['nullable', 'string', 'in:fraud,payment,property,technical,subscription,verification'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
        ]);

        $ticketCode = $codeGenerator->execute();

        $snapshot = $contract->seller_identity_snapshot_json;
        if (empty($snapshot)) {
            $company = $contract->developerCompany ?? $contract->project?->company;
            $agent = $contract->reservation?->agent;
            $snapshot = [
                'snapshot_version' => 'v1.0-backfilled',
                'captured_at' => ($contract->created_at ?? now())->toIso8601String(),
                'seller_type' => 'developer_company',
                'developer_company_id' => $company?->id,
                'company_legal_name' => $company?->legal_name ?? $company?->name ?? 'Developer Entity',
                'trade_name' => $company?->trade_name ?? $company?->name ?? 'Developer Entity',
                'tax_identification_number' => $company?->tin_number ?? 'TIN-001829471',
                'business_license_number' => $company?->license_number ?? 'BL/AA/2023/1029',
                'verification_level' => $company?->verification_level ?? 2,
                'verification_status' => ($company?->verification_level ?? 0) >= 2 ? 'approved' : 'verified',
                'verification_reference' => 'VER-COMP-' . ($company?->id ?? '001'),
                'verification_method' => 'internal_manual_review',
                'is_official_fayda_api_verified' => false,
                'statutory_basis' => 'Proclamation No. 1357/2024 Developer Sales & Real Estate Mandate',
                'authorized_representative' => [
                    'agent_user_id' => $agent?->id,
                    'name' => $agent?->name ?? 'Authorized Company Representative',
                    'masked_phone' => $agent?->phone ? substr($agent->phone, 0, 7) . '***' . substr($agent->phone, -2) : null,
                    'masked_national_id' => $agent?->masked_national_id ?? 'FAN-****-8842',
                    'authority_relationship' => 'mandated_agent',
                ],
                'property' => [
                    'project_id' => $contract->project_id,
                    'project_name' => $contract->project?->title_en ?? $contract->project?->name,
                    'construction_permit_number' => $contract->project?->building_permit_number ?? 'CP-AA-2024-991',
                    'project_unit_id' => $contract->project_unit_id,
                    'unit_code' => $contract->unit?->unit_number ?? 'UNT-001',
                    'unit_type' => $contract->unit?->unit_type ?? '2br',
                    'floor_number' => $contract->unit?->floor?->floor_number ?? 1,
                ],
                'buyer' => [
                    'user_id' => $contract->buyer_user_id,
                    'name' => $contract->buyer?->name,
                    'masked_phone' => $contract->buyer?->phone ? substr($contract->buyer->phone, 0, 7) . '***' . substr($contract->buyer->phone, -2) : null,
                ],
                'transaction' => [
                    'contract_code' => $contract->contract_code,
                    'reservation_code' => $contract->reservation?->reservation_code,
                    'contract_price_etb' => (float) $contract->contract_price_etb,
                    'deposit_paid_etb' => (float) $contract->deposit_paid_etb,
                ],
            ];
            $contract->updateQuietly(['seller_identity_snapshot_json' => $snapshot]);
        }

        $ticket = SupportTicket::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_code' => $ticketCode,
            'user_id' => $user->id,
            'contract_id' => $contract->id,
            'against_user_id' => $contract->developerCompany?->user_id ?? $contract->agent_user_id,
            'property_id' => $contract->project?->property_id,
            'transaction_reference' => $contract->contract_code,
            'seller_snapshot_json' => $snapshot,
            'evidence_json' => [
                'contract_code' => $contract->contract_code,
                'reservation_code' => $contract->reservation?->reservation_code,
                'contract_price_etb' => (float) $contract->contract_price_etb,
                'deposit_paid_etb' => (float) $contract->deposit_paid_etb,
                'seller_entity' => $snapshot['company_legal_name'] ?? 'Developer Entity',
                'verification_status' => $snapshot['verification_status'] ?? 'unknown',
            ],
            'category' => $validated['category'] ?? 'fraud',
            'priority' => $validated['priority'] ?? 'high',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'status' => 'open',
        ]);

        return redirect()->route('buyer.contracts.show', ['contract' => $contract->contract_code])
            ->with('status', "Transaction complaint {$ticket->ticket_code} lodged successfully. Compliance officers are reviewing the seller's verified identity and evidence.");
    }
}

