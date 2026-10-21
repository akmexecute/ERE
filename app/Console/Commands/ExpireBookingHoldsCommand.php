<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Booking\Actions\ExpireUnpaidBookingHoldsAction;
use Illuminate\Console\Command;

final class ExpireBookingHoldsCommand extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire unpaid short-stay temporary booking holds and release held calendar inventory (SRS Sections 49 & 193)';

    public function handle(ExpireUnpaidBookingHoldsAction $action): int
    {
        $expiredCount = $action->execute();

        if ($expiredCount > 0) {
            $this->info("Successfully expired {$expiredCount} unpaid booking hold(s) and released calendar inventory.");
        } else {
            $this->line('No unpaid booking holds expired.');
        }

        return self::SUCCESS;
    }
}
