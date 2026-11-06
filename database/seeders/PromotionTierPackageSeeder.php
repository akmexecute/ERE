<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Promotion\Enums\PromotionTier;
use App\Domains\Promotion\Models\PromotionTierPackage;
use Illuminate\Database\Seeder;

final class PromotionTierPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'tier' => PromotionTier::Standard->value,
                'name_en' => 'Standard Listing',
                'name_am' => 'መደበኛ ዝርዝር',
                'name_om' => 'Tarreeffama Idilee',
                'description_en' => 'Standard listing placement in regular search results without ranking boost.',
                'description_am' => 'ያለ ተጨማሪ የማስተዋወቂያ ደረጃ በመደበኛ የፍለጋ ውጤቶች ውስጥ የሚቀመጥ ዝርዝር።',
                'description_om' => 'Buʼaa barbaacha idilee keessatti dursa dabalataa malee kan tarreeffamu.',
                'price_etb' => 0.00,
                'duration_days' => 30,
                'ranking_weight' => 0,
                'badge_label' => null,
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => null,
            ],
            [
                'tier' => PromotionTier::Featured->value,
                'name_en' => 'Featured Listing',
                'name_am' => 'ተለይቶ የቀረበ ዝርዝር',
                'name_om' => 'Tarreeffama Filatamaa',
                'description_en' => 'Boosted visibility in search results with a gold badge and highlighted border.',
                'description_am' => 'በወርቃማ ባጅ እና በደመቀ መስመር በፍለጋ ውጤቶች ላይ የላቀ ታይነት።',
                'description_om' => 'Mallattoo warqee fi sarara ifaa taʼeen buʼaa barbaacha keessatti mulʼata guddaa.',
                'price_etb' => 800.00,
                'duration_days' => 14,
                'ranking_weight' => 10,
                'badge_label' => 'Featured',
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'tier' => PromotionTier::Premium->value,
                'name_en' => 'Premium Spotlight',
                'name_am' => 'ፕሪሚየም ስፖትላይት',
                'name_om' => 'Ibsaa Olaanaa',
                'description_en' => 'Top placement in search category results with premium badge and prioritized search indexing.',
                'description_am' => 'በፕሪሚየም ባጅ እና ቅድሚያ በሚሰጠው የፍለጋ ማውጫ በፍለጋ ውጤቶች አናት ላይ የሚገኝ።',
                'description_om' => 'Mallattoo olaanaa fi dursi kennamuufiin gubbaa barbaacha irratti kan mulʼatu.',
                'price_etb' => 1500.00,
                'duration_days' => 14,
                'ranking_weight' => 20,
                'badge_label' => 'Premium',
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'tier' => PromotionTier::Homepage->value,
                'name_en' => 'Homepage Showcase',
                'name_am' => 'የመነሻ ገጽ ማሳያ',
                'name_om' => 'Fuula Duraa Irratti Mulʼatu',
                'description_en' => 'Exclusive rotation on the ERE marketplace homepage hero carousel with maximum impressions.',
                'description_am' => 'በኢትዮጵያ ሪል እስቴት መነሻ ገጽ ላይ ከፍተኛ ጎብኚዎችን የሚስብ ማሳያ።',
                'description_om' => 'Fuula duraa irratti daawwattoota heddduudhaaf kan mulʼifamu.',
                'price_etb' => 2500.00,
                'duration_days' => 7,
                'ranking_weight' => 30,
                'badge_label' => 'Homepage Showcase',
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
            [
                'tier' => PromotionTier::Urgent->value,
                'name_en' => 'Urgent Listing',
                'name_am' => 'አስቸኳይ ሽያጭ / ኪራይ',
                'name_om' => 'Ariifachiisaa',
                'description_en' => 'High-visibility red urgency badge and priority matching for motivated buyers and tenants.',
                'description_am' => 'አስቸኳይ ለሆኑ ገዢዎችና ተከራዮች ቀዳሚ ሆኖ የሚቀርብ ቀይ የአስቸኳይ ባጅ።',
                'description_om' => 'Bittaa fi kireeffannaa ariifachiisaadhaaf mallattoo diimaa qabuun kan dhiyaatu.',
                'price_etb' => 500.00,
                'duration_days' => 7,
                'ranking_weight' => 15,
                'badge_label' => 'Urgent Sale',
                'is_active' => true,
                'is_public' => true,
                'eligible_plan_codes' => ['agent_pro', 'agency', 'developer', 'host', 'enterprise'],
            ],
        ];

        foreach ($packages as $pkg) {
            PromotionTierPackage::query()->updateOrCreate(
                ['tier' => $pkg['tier']],
                $pkg
            );
        }
    }
}

