<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Subscription\Enums\SubscriberType;
use App\Domains\Subscription\Models\SubscriptionFeature;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\SubscriptionPlanFeatureValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $featuresByKey = SubscriptionFeature::all()->keyBy('key');

        $plans = [
            [
                'code' => 'free',
                'name_en' => 'Free Basic',
                'name_am' => 'ነጻ መሰረታዊ',
                'name_om' => 'Bilisa Buʼuuraa',
                'description_en' => 'Essential property advertising for individual sellers and first-time landlords in Ethiopia.',
                'description_am' => 'ለግለሰብ ሻጮች እና ለአከራዮች የተዘጋጀ መሰረታዊ ነጻ ፓኬጅ።',
                'description_om' => 'Gurgurtoota dhuunfaa fi abbootii qabeenyaatiif kan qophaaʼe.',
                'subscriber_type' => SubscriberType::All,
                'price_etb' => 0.00,
                'duration_days' => 365,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'features' => [
                    'property.max_active_listings' => '2',
                    'property.max_images' => '8',
                    'property.video_uploads' => '0',
                    'property.virtual_tours' => 'false',
                    'property.room_hotspots' => 'false',
                    'property.featured_credits' => '0',
                    'property.analytics' => 'false',
                    'property.crm' => 'false',
                    'property.team_members' => '1',
                    'property.advanced_gis' => 'false',
                ],
            ],
            [
                'code' => 'agent_pro',
                'name_en' => 'Agent Pro',
                'name_am' => 'የደላላ ፕሮፌሽናል',
                'name_om' => 'Dilaalaa Piroo',
                'description_en' => 'Full-featured package for active independent real estate agents and licensed brokers.',
                'description_am' => 'ለንቁ ደላሎች እና ህጋዊ የሪል እስቴት ወኪሎች የተዘጋጀ ሙሉ አገልግሎት።',
                'description_om' => 'Dilaalota qajeeltoo fi ogeessota daldala qabeenyaaf.',
                'subscriber_type' => SubscriberType::Agent,
                'price_etb' => 3500.00,
                'duration_days' => 30,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'features' => [
                    'property.max_active_listings' => '25',
                    'property.max_images' => '20',
                    'property.video_uploads' => '3',
                    'property.virtual_tours' => 'true',
                    'property.room_hotspots' => 'true',
                    'property.featured_credits' => '3',
                    'property.analytics' => 'true',
                    'property.crm' => 'true',
                    'property.team_members' => '3',
                    'property.advanced_gis' => 'true',
                ],
            ],
            [
                'code' => 'agency',
                'name_en' => 'Real Estate Agency',
                'name_am' => 'የሪል እስቴት ኤጀንሲ',
                'name_om' => 'Dhaabbata Daldala Qabeenyaa',
                'description_en' => 'High-capacity corporate multi-seat platform for property brokerage agencies.',
                'description_am' => 'ለተደራጁ የሪል እስቴት ድርጅቶች እና የደላሎች ቡድን የተዘጋጀ።',
                'description_om' => 'Dhaabbilee gurguddaa fi gareewwan dilaalotaaf.',
                'subscriber_type' => SubscriberType::Company,
                'price_etb' => 12000.00,
                'duration_days' => 30,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'features' => [
                    'property.max_active_listings' => '100',
                    'property.max_images' => '40',
                    'property.video_uploads' => '10',
                    'property.virtual_tours' => 'true',
                    'property.room_hotspots' => 'true',
                    'property.featured_credits' => '15',
                    'property.analytics' => 'true',
                    'property.crm' => 'true',
                    'property.team_members' => '15',
                    'property.advanced_gis' => 'true',
                ],
            ],
            [
                'code' => 'developer',
                'name_en' => 'Property Developer',
                'name_am' => 'የሪል እስቴት አልሚ',
                'name_om' => 'Misoomsaa Qabeenyaa',
                'description_en' => 'Large-scale inventory tools for building developers showcasing multi-unit off-plan complexes.',
                'description_am' => 'ለአፓርታማ እና ቪላ አልሚዎች የተዘጋጀ የፕሮጀክትና የዩኒት መሸጫ ፓኬጅ።',
                'description_om' => 'Piroojektiiwwan gamoo fi gurgurtaa unitiitiif.',
                'subscriber_type' => SubscriberType::Developer,
                'price_etb' => 25000.00,
                'duration_days' => 30,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'features' => [
                    'property.max_active_listings' => '250',
                    'property.max_images' => '50',
                    'property.video_uploads' => '20',
                    'property.virtual_tours' => 'true',
                    'property.room_hotspots' => 'true',
                    'property.featured_credits' => '25',
                    'property.analytics' => 'true',
                    'property.crm' => 'true',
                    'property.team_members' => '25',
                    'property.advanced_gis' => 'true',
                ],
            ],
            [
                'code' => 'host',
                'name_en' => 'Short-Stay Host',
                'name_am' => 'የአጭር ጊዜ ቆይታ አስተናጋጅ',
                'name_om' => 'Keessummeessaa Turtii Gabaabaa',
                'description_en' => 'Automated calendars, booking tools, and guest communications for short-term rental operators.',
                'description_am' => 'ለእንግዳ ማረፊያ እና የቀን ኪራይ ቤቶች የተዘጋጀ የቦታ ማስያዣ ፓኬጅ።',
                'description_om' => 'Kireeffattoota guyyaa fi mana jireenyaa turtii gabaabaaf.',
                'subscriber_type' => SubscriberType::Host,
                'price_etb' => 2500.00,
                'duration_days' => 30,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => false,
                'sort_order' => 5,
                'features' => [
                    'property.max_active_listings' => '10',
                    'property.max_images' => '20',
                    'property.video_uploads' => '3',
                    'property.virtual_tours' => 'true',
                    'property.room_hotspots' => 'true',
                    'property.featured_credits' => '2',
                    'property.analytics' => 'true',
                    'property.crm' => 'false',
                    'property.team_members' => '2',
                    'property.advanced_gis' => 'false',
                ],
            ],
            [
                'code' => 'enterprise',
                'name_en' => 'Enterprise & Bank Assets',
                'name_am' => 'ኢንተርፕራይዝ እና የባንክ ንብረቶች',
                'name_om' => 'Dhaabbilee Gurguddoo fi Baankii',
                'description_en' => 'Unlimited capacity, customized API integrations, advanced PostGIS GIS intelligence, and dedicated support.',
                'description_am' => 'ወሰን የለሽ አቅም እና ከፍተኛ የቴክኖሎጂ ድጋፍ ያካተተ ትልቅ ፓኬጅ።',
                'description_om' => 'Dandeettii daangaa hin qabne fi deeggarsa olaanaa.',
                'subscriber_type' => SubscriberType::Company,
                'price_etb' => 45000.00,
                'duration_days' => 30,
                'is_active' => true,
                'is_public' => true,
                'is_featured' => false,
                'sort_order' => 6,
                'features' => [
                    'property.max_active_listings' => 'unlimited',
                    'property.max_images' => 'unlimited',
                    'property.video_uploads' => 'unlimited',
                    'property.virtual_tours' => 'true',
                    'property.room_hotspots' => 'true',
                    'property.featured_credits' => '50',
                    'property.analytics' => 'true',
                    'property.crm' => 'true',
                    'property.team_members' => 'unlimited',
                    'property.advanced_gis' => 'true',
                ],
            ],
        ];

        foreach ($plans as $p) {
            $featureAssignments = $p['features'];
            unset($p['features']);

            $plan = SubscriptionPlan::query()->firstOrCreate(
                ['code' => $p['code']],
                array_merge($p, ['uuid' => (string) Str::uuid()])
            );

            foreach ($featureAssignments as $fKey => $fVal) {
                $feature = $featuresByKey[$fKey] ?? null;
                if ($feature) {
                    SubscriptionPlanFeatureValue::query()->updateOrCreate(
                        ['plan_id' => $plan->id, 'feature_id' => $feature->id],
                        ['value' => (string) $fVal]
                    );
                }
            }
        }
    }
}

