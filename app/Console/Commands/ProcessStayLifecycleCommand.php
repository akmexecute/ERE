<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Dispute\Actions\ReleaseSecurityDepositAction;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\Property\Models\Property;
use App\Domains\Review\Models\StayReview;
use App\Domains\Stay\Actions\ProcessMissedCheckoutFallbackAction;
use App\Domains\Stay\Actions\ResolveAutomatedStayTriggersAction;
use App\Domains\Stay\Models\StayAutomatedMessageLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ProcessStayLifecycleCommand extends Command
{
    protected $signature = 'stay:process-lifecycle {--date= : Custom reference date YYYY-MM-DD}';
    protected $description = 'Process automated stay lifecycle reminders, missed checkouts, 48-hour deposit releases, and 14-day double-blind review publications.';

    public function handle(
        ResolveAutomatedStayTriggersAction $triggerResolver,
        ProcessMissedCheckoutFallbackAction $missedCheckoutAction,
        ReleaseSecurityDepositAction $releaseDepositAction
    ): int {
        $referenceDate = (string) ($this->option('date') ?: CarbonImmutable::now()->toDateString());
        $now = CarbonImmutable::parse($referenceDate);

        $this->info("Processing Stay Lifecycle for reference date: {$referenceDate}");

        // 1. Process Automated Stay Lifecycle Reminders
        $activeBookings = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Active, BookingStatus::CheckedOut])
            ->get();

        $remindersCount = 0;
        foreach ($activeBookings as $booking) {
            $alreadySent = StayAutomatedMessageLog::query()
                ->where('booking_id', $booking->id)
                ->pluck('trigger_code')
                ->all();

            $dueTriggers = $triggerResolver->execute(
                bookingStatus: $booking->status->value,
                checkInDate: $booking->check_in_date->toDateString(),
                checkOutDate: $booking->check_out_date->toDateString(),
                referenceDate: $referenceDate,
                alreadySentTriggers: $alreadySent
            );

            // Add host credential check trigger (24 hours prior to check-in)
            $checkInDate = CarbonImmutable::parse($booking->check_in_date)->startOfDay();
            if ($now->diffInDays($checkInDate, false) === 1 && ! in_array('host_credential_check', $alreadySent, true)) {
                $dueTriggers[] = 'host_credential_check';
            }

            foreach ($dueTriggers as $trigger) {
                StayAutomatedMessageLog::query()->firstOrCreate(
                    ['booking_id' => $booking->id, 'trigger_code' => $trigger],
                    ['channel' => 'in_app', 'sent_at' => CarbonImmutable::now()]
                );
                $remindersCount++;
            }
        }
        $this->line("• Dispatched {$remindersCount} lifecycle reminder triggers.");

        // 2. Process Missed Checkout Fallbacks (Rule: missing report does NOT forfeit deposit)
        $missed = $missedCheckoutAction->execute($referenceDate);
        $this->line('• Handled '.count($missed).' missed checkout fallback cases.');

        // 3. Process 48-Hour Deposit Release for Clean Checkouts with No Claims
        $claimCutoff = $now->subHours(48);
        $eligibleDeposits = SecurityDeposit::query()
            ->where('status', 'held')
            ->whereHas('booking.checkOut', function ($q) use ($claimCutoff): void {
                $q->where('checked_out_at', '<=', $claimCutoff);
            })
            ->whereDoesntHave('damageClaims', function ($q): void {
                $q->whereIn('status', ['submitted', 'disputed_by_guest']);
            })
            ->get();

        $releasedCount = 0;
        foreach ($eligibleDeposits as $deposit) {
            try {
                $releaseDepositAction->execute($deposit);
                $releasedCount++;
            } catch (\Throwable $e) {
                $this->warn("Failed to release deposit #{$deposit->id}: {$e->getMessage()}");
            }
        }
        $this->line("• Automatically released {$releasedCount} eligible security deposits past the 48h claim window.");

        // 4. Auto-Publish Eligible Reviews Past 14-Day Deadline
        $reviewCutoff = $now->subDays(14);
        $expiredReviews = StayReview::query()
            ->where('is_published', false)
            ->where('moderation_status', 'approved')
            ->whereHas('booking.checkOut', function ($q) use ($reviewCutoff): void {
                $q->where('checked_out_at', '<=', $reviewCutoff);
            })
            ->get();

        $publishedReviews = 0;
        foreach ($expiredReviews as $review) {
            $review->update([
                'is_published' => true,
                'published_at' => CarbonImmutable::now(),
            ]);
            $publishedReviews++;

            // Recalculate host ratings
            if ($review->direction === 'guest_to_host') {
                $hostStats = StayReview::query()
                    ->where('host_id', $review->host_id)
                    ->where('direction', 'guest_to_host')
                    ->where('is_published', true)
                    ->where('moderation_status', 'approved')
                    ->selectRaw('COUNT(*) as total, AVG(overall_rating) as avg_rating')
                    ->first();

                if ($hostStats && $hostStats->total > 0) {
                    DB::table('hosts')->where('id', $review->host_id)->update([
                        'average_rating' => round((float) $hostStats->avg_rating, 2),
                        'reviews_count' => (int) $hostStats->total,
                    ]);
                }
            }
        }
        $this->line("• Published {$publishedReviews} eligible single-sided reviews past the 14-day double-blind window.");

        return Command::SUCCESS;
    }
}
