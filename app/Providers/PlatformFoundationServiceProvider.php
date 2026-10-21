<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class PlatformFoundationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Renders dual-calendar interactive span toggled by Alpine.js $store.preferences.calendar
        Blade::directive('ethDate', function (string $expression): string {
            return "<?php
                \$__date = {$expression};
                if (\$__date) {
                    \$__eth = \\App\\Support\\Calendar\\EthiopianCalendarConverter::fromGregorian(\$__date)->format(app()->getLocale());
                    \$__greg = \\Carbon\\CarbonImmutable::parse(\$__date)->format('M d, Y');
                    echo '<span x-data x-text=\"\$store.preferences.calendar === \'ethiopian\' ? \'' . addslashes(\$__eth) . '\' : \'' . addslashes(\$__greg) . '\'\" title=\"' . e(\$__eth . ' | ' . \$__greg) . '\">' . e(\$__eth) . '</span>';
                }
            ?>";
        });

        Blade::directive('etb', function (string $expression): string {
            return "<?php echo e(\\App\\Support\\Money\\EtbFormatter::format({$expression})); ?>";
        });
    }
}
