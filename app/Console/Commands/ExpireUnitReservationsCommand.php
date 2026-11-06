<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Developer\Actions\ExpireStaleReservationsAction;
use Illuminate\Console\Command;

class ExpireUnitReservationsCommand extends Command
{
    protected $signature = 'reservations:expire-stale';

    protected $description = 'Expire stale unit reservation holds and release inventory back to available';

    public function handle(ExpireStaleReservationsAction $action): int
    {
        $count = $action->execute();
        $this->info("Expired {$count} stale unit reservation holds.");

        return Command::SUCCESS;
    }
}
