<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacRolePermissionSeeder::class,
            EthiopianLocationsSeeder::class,
            PropertyTaxonomySeeder::class,
            ChartOfAccountsSeeder::class,
            SubscriptionFeatureSeeder::class,
            SubscriptionPlanSeeder::class,
            PromotionTierPackageSeeder::class,
            AdPlacementSeeder::class,
        ]);
    }
}
