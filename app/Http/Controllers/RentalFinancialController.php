<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Actions\AppendRentalPaymentAdjustmentAction;
use App\Domains\RentalFinancial\Actions\ConfirmRentalPaymentAction;
use App\Domains\RentalFinancial\Actions\RecordRentalPaymentAction;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentalReceipt;
use App\Domains\RentalFinancial\Models\RentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RentalFinancialController extends Controller
{
    public function schedule(string $leaseUuid, Request $request): View
    {
        $user = $request->user();
        /** @var Lease $lease */
        $lease = Lease::query()
            ->where('uuid', $leaseUuid)
            ->with([
                'property',
                'projectUnit',
                'tenant',
                'landlord',
                'rentSchedules.payments.receipt',
                'rentSchedules.adjustments.authorizedBy',
            ])
            ->firstOrFail();

        $isTenant = ($user->id === $lease->tenant_id);
        $isLandlord = ($user->id === $lease->landlord_id || ($lease->company_id && $user->company_id === $lease->company_id));
        $isStaff = $user->hasAnyRole(['finance_officer', 'property_admin', 'platform_admin', 'super_admin']);

        if (! $isTenant && ! $isLandlord && ! $isStaff) {
            abort(403, 'Unauthorized access to rent schedules.');
        }

        return view('tenant.rent.schedule', [
            'lease' => $lease,
            'isTenant' => $isTenant,
            'isLandlord' => $isLandlord,
            'isStaff' => $isStaff,
        ]);
    }

    public function recordPayment(
        string $scheduleUuid,
        Request $request,
        RecordRentalPaymentAction $recordAction
    ): RedirectResponse {
        $schedule = RentSchedule::query()->where('uuid', $scheduleUuid)->firstOrFail();

        $validated = $request->validate([
            'amount_etb' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:cbe_birr,telebirr,bank_transfer,cash,chapa',
            'provider_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:300',
            'idempotency_key' => 'nullable|string|max:80',
        ]);

        $payment = $recordAction->execute($request->user(), [
            'lease_id' => $schedule->lease_id,
            'rent_schedule_id' => $schedule->id,
            'amount_etb' => $validated['amount_etb'],
            'payment_method' => $validated['payment_method'],
            'provider_reference' => $validated['provider_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ]);

        return redirect()->route('rent.schedule', $schedule->lease->uuid)
            ->with('status', "Payment #{$payment->payment_reference} recorded and pending landlord confirmation.");
    }

    public function confirmPayment(
        string $paymentUuid,
        Request $request,
        ConfirmRentalPaymentAction $confirmAction
    ): RedirectResponse {
        $payment = RentalPayment::query()->where('uuid', $paymentUuid)->firstOrFail();

        $receipt = $confirmAction->execute($request->user(), $payment);

        return redirect()->route('rent.receipt', $receipt->uuid)
            ->with('status', "Payment confirmed! Official Receipt #{$receipt->receipt_number} issued.");
    }

    public function receipt(string $uuid, Request $request): View
    {
        $user = $request->user();
        /** @var RentalReceipt $receipt */
        $receipt = RentalReceipt::query()
            ->where('uuid', $uuid)
            ->with(['payment', 'lease.property', 'rentSchedule', 'payer', 'recipient'])
            ->firstOrFail();

        $isParty = ($user->id === $receipt->payer_user_id || $user->id === $receipt->recipient_user_id);
        $isStaff = $user->hasAnyRole(['finance_officer', 'property_admin', 'platform_admin', 'super_admin']);

        if (! $isParty && ! $isStaff) {
            abort(403, 'Unauthorized access to rental receipt.');
        }

        return view('tenant.rent.receipt', [
            'receipt' => $receipt,
        ]);
    }

    public function adjustPayment(
        string $paymentUuid,
        Request $request,
        AppendRentalPaymentAdjustmentAction $adjustAction
    ): RedirectResponse {
        $payment = RentalPayment::query()->where('uuid', $paymentUuid)->firstOrFail();

        $validated = $request->validate([
            'adjustment_amount_etb' => 'required|numeric|not_in:0',
            'reason' => 'required|string|min:5|max:300',
        ]);

        $adjustment = $adjustAction->execute(
            $request->user(),
            $payment,
            (float) $validated['adjustment_amount_etb'],
            $validated['reason']
        );

        return redirect()->route('rent.schedule', $payment->lease->uuid)
            ->with('status', "Append-only financial adjustment of ETB {$adjustment->adjustment_amount_etb} recorded.");
    }
}
