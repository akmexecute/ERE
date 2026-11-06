<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Advertising\Enums\AdPlacementType;
use App\Domains\Advertising\Models\AdPlacement;
use Illuminate\Database\Seeder;

final class AdPlacementSeeder extends Seeder
{
    public function run(): void
    {
        $placements = [
            [
                'placement' => AdPlacementType::Homepage->value,
                'name_en' => 'National Homepage Hero Banner',
                'name_am' => 'የብሔራዊ መነሻ ገጽ ባነር',
                'name_om' => 'Baanara Fuula Duraa Biyyooleessaa',
                'description_en' => 'Prominent high-impact display banner on the ERE national home page.',
                'price_etb' => 3500.00,
                'duration_days' => 30,
                'impression_limit' => 50000,
                'click_limit' => 2500,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::Search->value,
                'name_en' => 'Marketplace Search Top Spot',
                'name_am' => 'የፍለጋ ውጤቶች አናት ስፖንሰር',
                'name_om' => 'Buʼaa Barbaacha Gubbaa Irratti',
                'description_en' => 'Pinned promotional sponsorship banner at the top of search result pages.',
                'price_etb' => 2500.00,
                'duration_days' => 30,
                'impression_limit' => 35000,
                'click_limit' => 1800,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::CityPage->value,
                'name_en' => 'City / Regional Hub Spotlight',
                'name_am' => 'የከተማ እና ክልል ማዕከል ማስተዋወቂያ',
                'name_om' => 'Magaalaa fi Naannoo Keessatti',
                'description_en' => 'Targeted advertising on specific regional and municipal directory hubs.',
                'price_etb' => 1800.00,
                'duration_days' => 30,
                'impression_limit' => 20000,
                'click_limit' => 1000,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::CategoryPage->value,
                'name_en' => 'Category-Specific Native Card',
                'name_am' => 'የዘርፍ-ተኮር ማስታወቂያ ካርድ',
                'name_om' => 'Kaardii Gosa Qabeenyaa Irratti',
                'description_en' => 'Native advertisement card matched directly to property taxonomies.',
                'price_etb' => 1500.00,
                'duration_days' => 30,
                'impression_limit' => 15000,
                'click_limit' => 800,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::PropertyPage->value,
                'name_en' => 'Property Detail Sidebar Banner',
                'name_am' => 'የንብረት ዝርዝር ገጽ ጎንዮሽ ባነር',
                'name_om' => 'Baanara Cinaa Fuula Qabeenyaa',
                'description_en' => 'Contextual sidebar banner shown to active buyers viewing property specifications.',
                'price_etb' => 1200.00,
                'duration_days' => 30,
                'impression_limit' => 12000,
                'click_limit' => 600,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::Mobile->value,
                'name_en' => 'Mobile Sticky / Native Feed',
                'name_am' => 'የሞባይል ስልክ ተንሳፋፊ ባነር',
                'name_om' => 'Baanara Moobaayilaa',
                'description_en' => 'High-conversion responsive banner optimized for mobile viewport browsing.',
                'price_etb' => 2000.00,
                'duration_days' => 30,
                'impression_limit' => 25000,
                'click_limit' => 1200,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'placement' => AdPlacementType::Newsletter->value,
                'name_en' => 'Investor Weekly Digest Sponsorship',
                'name_am' => 'የባለሀብቶች ሳምንታዊ ጋዜጣ ስፖንሰር',
                'name_om' => 'Oduu Torbee Abbootii Qabeenyaa',
                'description_en' => 'Featured sponsor spotlight in the weekly real estate investment email digest.',
                'price_etb' => 1000.00,
                'duration_days' => 14,
                'impression_limit' => 5000,
                'click_limit' => 400,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agency', 'developer', 'enterprise'],
            ],
        ];

        foreach ($placements as $plc) {
            AdPlacement::query()->updateOrCreate(
                ['placement' => $plc['placement']],
                $plc
            );
        }
    }
}

