<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Engagement\Actions\ProcessSavedSearchAlertsAction;
use Illuminate\Console\Command;

final class ProcessSavedSearchAlertsCommand extends Command
{
    protected $signature = 'alerts:process-saved-searches {--frequency=all : Frequency to process: instant, daily, weekly, or all}';

    protected $description = 'Evaluate newly published listings against active saved searches and dispatch alerts (SRS Sections 18, 124, 214)';

    public function handle(ProcessSavedSearchAlertsAction $action): int
    {
        $frequency = (string) $this->option('frequency');
        $this->info("Processing saved search alerts (frequency: {$frequency})...");

        $result = $action->execute($frequency === 'all' ? null : $frequency);

        $this->info("Saved searches evaluated: {$result['searches_evaluated']}");
        $this->info("Alerts dispatched: {$result['alerts_dispatched']}");
        $this->info("Total matching listings found: {$result['total_matches']}");

        return Command::SUCCESS;
    }
}
