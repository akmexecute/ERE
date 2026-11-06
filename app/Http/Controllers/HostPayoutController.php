<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Financial\Actions\HoldOrReleasePayoutAction;
use App\Domains\Financial\Actions\ProcessHostPayoutAction;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Host\Models\Host;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HostPayoutController extends Controller
{
    public function __construct(
        private readonly ProcessHostPayoutAction $processAction,
        private readonly HoldOrReleasePayoutAction $holdAction
    ) {}

    /**
     * Host Payout Console: overview of waterfall balances and upcoming schedules.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $host = Host::query()->where('user_id', $user->id)->with('payoutDestinations')->first();

        $query = HostPayout::query()->with(['booking', 'destination', 'adjustments']);
        if ($host && ! $user->hasRole('super_admin')) {
            $query->where('host_id', $host->id);
        }

        $payouts = $query->orderByDesc('scheduled_for')->paginate(15);

        // Compute summary waterfall metrics
        $grossSum = (float) (clone $query)->sum('gross_revenue_etb');
        $deductionsSum = (float) (clone $query)->sum('platform_deductions_etb');
        $adjustmentsSum = (float) (clone $query)->sum('adjustments_amount_etb');
        $netSum = (float) (clone $query)->sum('net_payable_etb');

        return view('payments.checkout-and-payouts', [
            'payouts' => $payouts,
            'host' => $host,
            'summary' => [
                'gross_revenue_etb' => $grossSum,
                'platform_deductions_etb' => $deductionsSum,
                'adjustments_amount_etb' => $adjustmentsSum,
                'net_payable_etb' => $netSum,
            ],
            'viewMode' => 'payouts',
        ]);
    }

    /**
     * Place payout on hold.
     */
    public function hold(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:400'],
        ]);

        $payout = HostPayout::query()
            ->where(function ($q) use ($uuid): void {
                $q->where('uuid', $uuid);
                if (is_numeric($uuid)) {
                    $q->orWhere('id', (int) $uuid);
                }
            })
            ->firstOrFail();
        $updated = $this->holdAction->hold($payout, (string) $request->input('reason'), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $updated->status->value,
                'message' => 'Payout placed on hold.',
            ]);
        }

        return back()->with('status', 'Payout placed on hold.');
    }

    /**
     * Release payout from hold.
     */
    public function release(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $payout = HostPayout::query()
            ->where(function ($q) use ($uuid): void {
                $q->where('uuid', $uuid);
                if (is_numeric($uuid)) {
                    $q->orWhere('id', (int) $uuid);
                }
            })
            ->firstOrFail();
        $updated = $this->holdAction->release($payout, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $updated->status->value,
                'message' => 'Payout released from hold.',
            ]);
        }

        return back()->with('status', 'Payout released.');
    }

    /**
     * Settle an eligible host payout.
     */
    public function process(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $payout = HostPayout::query()
            ->where(function ($q) use ($uuid): void {
                $q->where('uuid', $uuid);
                if (is_numeric($uuid)) {
                    $q->orWhere('id', (int) $uuid);
                }
            })
            ->firstOrFail();
        $settled = $this->processAction->execute($payout, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $settled->status->value,
                'reference' => $settled->provider_reference,
                'message' => 'Payout successfully settled and posted to ledger.',
            ]);
        }

        return back()->with('status', 'Payout settled successfully.');
    }
}
