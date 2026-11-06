<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Administration\Services\SettingService;
use App\Domains\Identity\Contracts\SmsGatewayInterface;
use App\Domains\Identity\Services\ArraySmsGateway;
use App\Domains\Identity\Services\EthiopianSmsGateway;
use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class PlatformFoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class, fn () => new SettingService());

        $this->app->bind(SmsGatewayInterface::class, function () {
            if ($this->app->environment('testing') || config('services.sms.driver') === 'array') {
                return new ArraySmsGateway();
            }

            return new EthiopianSmsGateway();
        });

        $this->app->extend('translation.loader', function ($loader, $app) {
            return new \App\Domains\Localization\Services\DatabaseTranslationLoader($loader);
        });
    }

    public function boot(): void
    {
        // Renders dual-calendar interactive span toggled by Alpine.js $store.preferences.calendar
        Blade::directive('ethDate', function (string $expression): string {
            return "<?php
                \$__date = {$expression};
                if (\$__date) {
                    echo view('components.calendar-date', ['date' => \$__date])->render();
                }
            ?>";
        });

        Blade::directive('etb', function (string $expression): string {
            return "<?php echo e(\\App\\Support\\Money\\EtbFormatter::format({$expression})); ?>";
        });
    }
}
