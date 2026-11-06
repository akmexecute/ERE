<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Administration\Services\AuditService;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Actions\SubmitBankTransferSlipAction;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Services\PaymentManager;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly InitiatePaymentAction $initiateAction,
        private readonly SubmitBankTransferSlipAction $submitSlipAction,
        private readonly DoubleEntryPostingService $postingService
    ) {}

    /**
     * Unified Payment Checkout & Payout Interface.
     */
    public function checkout(Request $request): View
    {
        $user = $request->user();
        $payableType = $request->query('payable_type');
        $payableId = $request->query('payable_id');

        $payable = null;
        $serverPricing = null;
        $purchaseDetails = null;

        if ($payableType && $payableId) {
            if (! in_array($payableType, Payment::ALLOWED_PAYABLE_TYPES, true)) {
                throw new DomainException("Invalid payable type [{$payableType}].");
            }

            $payable = $payableType::query()->findOrFail($payableId);
            $serverPricing = $this->paymentManager->calculateServerAmounts($payable);

            if ($payable instanceof PropertyOffer) {
                $agreedPrice = (float) $payable->latest_amount_etb;
                $totalPaid = $payable->calculateTotalPaidEtb();
                $remaining = $payable->calculateRemainingBalanceEtb();

                $purchaseDetails = [
                    'agreed_price_etb' => $agreedPrice,
                    'total_paid_etb' => $totalPaid,
                    'remaining_balance_etb' => $remaining,
                    'deal_type' => 'Secondary / Direct Sale',
                    'offer_code' => $payable->offer_code,
                    'property_title' => $payable->listing?->title_en ?? ($payable->property?->title_en ?? 'Property #' . $payable->property_id),
                    'is_fully_paid' => $payable->isFullyPaid(),
                ];
            } elseif ($payable instanceof \App\Domains\Developer\Models\DeveloperSalesContract) {
                $agreedPrice = (float) $payable->contract_price_etb;
                $totalPaid = $payable->calculateTotalPaidEtb();
                $remaining = $payable->calculateRemainingBalanceEtb();

                $purchaseDetails = [
                    'agreed_price_etb' => $agreedPrice,
                    'total_paid_etb' => $totalPaid,
                    'remaining_balance_etb' => $remaining,
                    'deal_type' => 'Developer Off-Plan Contract',
                    'offer_code' => $payable->contract_code,
                    'property_title' => $payable->project?->title_en ?? 'Developer Project #' . $payable->project_id,
                    'is_fully_paid' => $payable->isFullyPaid(),
                ];
            } elseif ($payable instanceof \App\Domains\Developer\Models\ContractInstallment) {
                $agreedPrice = (float) $payable->amount_etb;
                $totalPaid = $payable->calculateTotalPaidEtb();
                $remaining = $payable->calculateOutstandingBalanceEtb();

                $purchaseDetails = [
                    'agreed_price_etb' => $agreedPrice,
                    'total_paid_etb' => $totalPaid,
                    'remaining_balance_etb' => $remaining,
                    'deal_type' => 'Milestone Installment #' . $payable->sequence_number,
                    'offer_code' => ($payable->contract?->contract_code ?? 'CNT') . " (Milestone #{$payable->sequence_number})",
                    'property_title' => $payable->contract?->project?->title_en ?? 'Developer Installment',
                    'is_fully_paid' => $payable->isFullyPaid(),
                ];
            } elseif ($payable instanceof \App\Domains\Subscription\Models\UserSubscription) {
                $agreedPrice = (float) $payable->price_etb;
                $purchaseDetails = [
                    'agreed_price_etb' => $agreedPrice,
                    'total_paid_etb' => $payable->status->value === 'active' ? $agreedPrice : 0.0,
                    'remaining_balance_etb' => $payable->status->value === 'active' ? 0.0 : $agreedPrice,
                    'deal_type' => 'Platform Subscription: ' . ($payable->plan?->name_en ?? 'Plan'),
                    'offer_code' => $payable->uuid,
                    'property_title' => 'Subscription: ' . ($payable->plan?->name_en ?? 'Plan'),
                    'is_fully_paid' => $payable->status->value === 'active',
                ];
            }
        }

        return view('payments.checkout-and-payouts', [
            'payable' => $payable,
            'serverPricing' => $serverPricing,
            'purchaseDetails' => $purchaseDetails,
            'user' => $user,
            'paymentMethods' => PaymentMethod::cases(),
            'paymentTypes' => PaymentType::cases(),
        ]);
    }

    /**
     * Create an idempotent payment intent.
     */
    public function initiate(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'payable_type' => ['required', 'string', 'in:' . implode(',', Payment::ALLOWED_PAYABLE_TYPES)],
            'payable_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'custom_amount_etb' => ['nullable', 'numeric', 'min:1'],
            'payment_type' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $payable = $validated['payable_type']::query()->findOrFail($validated['payable_id']);

        try {
            $result = $this->initiateAction->execute($request->user(), [
                'payable' => $payable,
                'payment_method' => $validated['payment_method'],
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'custom_amount_etb' => isset($validated['custom_amount_etb']) ? (float) $validated['custom_amount_etb'] : null,
                'payment_type' => $validated['payment_type'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (\DomainException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'payment' => [
                    'uuid' => $result['payment']->uuid,
                    'transaction_id' => $result['payment']->transaction_id,
                    'reference_code' => $result['payment']->reference_code,
                    'amount_etb' => (float) $result['payment']->total_amount_etb,
                    'status' => $result['payment']->status->value,
                ],
                'provider' => $result['provider_response'],
                'is_duplicate' => $result['is_duplicate_replay'],
            ]);
        }

        return redirect()->route('payments.show', $result['payment']->uuid)
            ->with('status', 'Payment intent initiated successfully.');
    }

    /**
     * Upload manual bank deposit slip.
     */
    public function submitBankSlip(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $payment = Payment::query()
            ->where(function ($q) use ($uuid): void {
                $q->where('uuid', $uuid);
                if (is_numeric($uuid)) {
                    $q->orWhere('id', (int) $uuid);
                }
            })
            ->firstOrFail();

        $validated = $request->validate([
            'slip_file' => ['required', 'file', 'mimes:jpeg,png,webp,pdf', 'max:10240'],
            'transfer_reference' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'account_holder_name' => ['nullable', 'string', 'max:160'],
            'deposited_amount_etb' => ['nullable', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $proof = $this->submitSlipAction->execute(
            $payment,
            $request->user(),
            $request->file('slip_file'),
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'proof_uuid' => $proof->uuid,
                'status' => $proof->verification_status->value,
                'message' => 'Bank transfer slip uploaded. Awaiting officer verification.',
            ]);
        }

        return redirect()->route('payments.show', $payment->uuid)
            ->with('status', 'Bank transfer slip uploaded. Verification pending.');
    }

    /**
     * Display payment status and receipt.
     */
    public function show(Request $request, string $uuid): View
    {
        $payment = Payment::query()
            ->with(['bankTransferProof', 'customer'])
            ->where(function ($q) use ($uuid): void {
                $q->where('uuid', $uuid)
                    ->orWhere('reference_code', $uuid);
                if (is_numeric($uuid)) {
                    $q->orWhere('id', (int) $uuid);
                }
            })
            ->firstOrFail();

        // Customer access authorization
        if ($payment->customer_user_id !== $request->user()->id && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized to view this transaction record.');
        }

        return view('payments.show', [
            'payment' => $payment,
        ]);
    }

    /**
     * View payments requiring administrative financial reconciliation (e.g. late callbacks exceeding balance).
     */
    public function reconciliationIndex(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin')),
            403,
            'Unauthorized. Administrative financial clearance required to view reconciliation console.'
        );

        $exceptions = Payment::query()
            ->with(['customer', 'payable'])
            ->where('allocation_status', PurchaseAllocationStatus::ReconciliationRequired)
            ->latest()
            ->paginate(20);

        return view('payments.reconciliation', [
            'exceptions' => $exceptions,
        ]);
    }

    /**
     * Process authorized refund for unapplied customer funds.
     */
    public function refundUnapplied(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin')),
            403,
            'Unauthorized. Administrative financial clearance required to authorize refunds.'
        );

        $payment = Payment::query()
            ->where('uuid', $uuid)
            ->orWhere('id', is_numeric($uuid) ? (int) $uuid : 0)
            ->lockForUpdate()
            ->firstOrFail();

        if ($payment->allocation_status !== PurchaseAllocationStatus::ReconciliationRequired) {
            throw new DomainException("Payment [{$payment->reference_code}] does not require unapplied reconciliation.");
        }

        $refundAmount = (float) $payment->unapplied_amount_etb;
        $reason = $request->input('reason', 'Administrative refund of unapplied customer funds due to contractual balance limit.');

        // Stage 1 of Two-Stage Settlement: Debit 2040 Unapplied Funds, Credit 2050 Customer Refunds Payable
        $postingGroup = $this->postingService->postPaymentRefund($payment, $refundAmount, $reason, $user, false);

        // Record centralized audit trail
        app(AuditService::class)->log(
            'reconciliation_refund_authorized',
            $payment,
            [
                'allocation_status' => 'reconciliation_required',
                'unapplied_amount_etb' => (string) $payment->unapplied_amount_etb,
                'status' => $payment->status->value,
            ],
            [
                'allocation_status' => 'refunded',
                'refunded_amount_etb' => (string) $refundAmount,
                'status' => 'refunded',
                'disbursement_mode' => 'manual_banking_disbursement_authorized',
                'provider_settlement_confirmed' => false,
            ],
            $reason,
            $user->id
        );

        // Record operational settlement metadata (distinguishing internal ledger release from external provider settlement)
        $meta = $payment->metadata_json ?? [];
        $meta['refund_authorization'] = [
            'authorized_by_user_id' => $user->id,
            'authorized_by_name' => $user->name,
            'authorized_at' => now()->toIso8601String(),
            'reason' => $reason,
            'ledger_recorded' => true,
            'ledger_posting_group_id' => $postingGroup->id,
            'disbursement_status' => 'pending_manual_disbursement',
            'provider_settlement_confirmed' => false,
        ];
        $payment->metadata_json = $meta;
        $payment->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Unapplied customer funds refunded successfully.',
                'payment_status' => $payment->status->value,
                'allocation_status' => $payment->allocation_status->value,
                'ledger_recorded' => true,
                'provider_settlement_confirmed' => false,
            ]);
        }

        return back()->with('status', 'Unapplied customer funds refunded successfully.');
    }

    /**
     * Confirm external disbursement of an authorized refund (Stage 2 of Two-Stage Settlement).
     * Clears Customer Refunds Payable (Account 2050) and credits Cash & Clearing (Account 1010).
     */
    public function confirmRefundDisbursement(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin')),
            403,
            'Unauthorized. Administrative financial clearance required to confirm refund disbursements.'
        );

        $validated = $request->validate([
            'disbursement_reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = Payment::query()
            ->where('uuid', $uuid)
            ->orWhere('id', is_numeric($uuid) ? (int) $uuid : 0)
            ->lockForUpdate()
            ->firstOrFail();

        $meta = $payment->metadata_json ?? [];
        if (! isset($meta['refund_authorization'])) {
            throw new DomainException("Payment [{$payment->reference_code}] does not have an authorized refund pending disbursement.");
        }

        if (! empty($meta['refund_settlement']['provider_settlement_confirmed'])) {
            throw new DomainException("Disbursement for payment [{$payment->reference_code}] has already been settled.");
        }

        $postingGroup = $this->postingService->confirmRefundDisbursement(
            $payment,
            $validated['disbursement_reference'],
            $user
        );

        // Record centralized audit log
        app(AuditService::class)->log(
            'reconciliation_refund_disbursed',
            $payment,
            [
                'provider_settlement_confirmed' => false,
                'disbursement_status' => 'pending_manual_disbursement',
            ],
            [
                'provider_settlement_confirmed' => true,
                'disbursement_status' => 'settled',
                'disbursement_reference' => $validated['disbursement_reference'],
                'disbursed_at' => now()->toIso8601String(),
            ],
            $validated['notes'] ?? 'External refund payout confirmed via provider/bank transfer.',
            $user->id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Refund disbursement confirmed and posted to general ledger.',
                'payment_status' => $payment->status->value,
                'provider_settlement_confirmed' => true,
                'disbursement_reference' => $validated['disbursement_reference'],
                'ledger_posting_group_id' => $postingGroup->id,
            ]);
        }

        return back()->with('status', 'Refund disbursement confirmed successfully.');
    }
}
