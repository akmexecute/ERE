<?php

declare(strict_types=1);

namespace App\Domains\Financial\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Financial\Models\HostPayoutDestination;
use App\Domains\Financial\Services\HostPayoutCalculator;
use App\Domains\Host\Models\Host;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ScheduleHostPayoutAction
{
    public function __construct(
        private readonly HostPayoutCalculator $calculator
    ) {}

    /**
     * Schedule a host payout for a confirmed short-stay booking.
     */
    public function execute(Booking $booking): HostPayout
    {
        return DB::transaction(function () use ($booking) {
            // Ensure no duplicate active payout exists for this booking
            $existing = HostPayout::query()
                ->where('booking_id', $booking->id)
                ->whereNotIn('status', [PayoutStatus::Cancelled->value, PayoutStatus::Failed->value])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $host = $booking->host;
            if (! $host) {
                throw new DomainException("Booking [{$booking->booking_code}] has no assigned host.");
            }

            // Determine host payout schedule preference
            $scheduleEnum = PayoutSchedule::tryFrom($host->payout_schedule ?? 'after_checkin')
                ?? PayoutSchedule::AfterCheckin;

            // Waterfall calculation: Gross Accommodation - Platform Host Fee
            // In ERE, accommodation_net_subtotal_etb is gross accommodation revenue
            $grossRevenue = (float) $booking->accommodation_net_subtotal_etb;
            $platformFee = (float) $booking->platform_fee_etb;

            $waterfall = $this->calculator->calculateWaterfall(
                $grossRevenue,
                $platformFee,
                0.00 // initial adjustments
            );

            $scheduledFor = $this->calculator->resolveScheduledReleaseDate($scheduleEnum, $booking);

            // Locate primary verified payout destination for host
            $destination = HostPayoutDestination::query()
                ->where('host_id', $host->id)
                ->where('is_verified', true)
                ->orderByDesc('is_primary')
                ->first();

            return HostPayout::query()->create([
                'host_id' => $host->id,
                'booking_id' => $booking->id,
                'payout_destination_id' => $destination?->id,
                'schedule_type' => $scheduleEnum,
                'scheduled_for' => $scheduledFor,
                'gross_revenue_etb' => $waterfall['gross_revenue_etb'],
                'platform_deductions_etb' => $waterfall['platform_deductions_etb'],
                'adjustments_amount_etb' => $waterfall['adjustments_amount_etb'],
                'net_payable_etb' => $waterfall['net_payable_etb'],
                'currency' => 'ETB',
                'status' => PayoutStatus::Scheduled,
            ]);
        });
    }
}

