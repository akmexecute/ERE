<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Subscription\Enums\FeatureValueType;
use App\Domains\Subscription\Models\SubscriptionFeature;
use Illuminate\Database\Seeder;

final class SubscriptionFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'key' => 'property.max_active_listings',
                'name_en' => 'Active Property Listings',
                'name_am' => 'ንቁ የንብረት ማስታወቂያዎች ብዛት',
                'name_om' => 'Baayʼina Beeksisa Qabeenyaa Sochoʼaa',
                'description_en' => 'Maximum number of simultaneously published active marketplace listings.',
                'category' => 'listings',
                'value_type' => FeatureValueType::Integer,
                'default_value' => '2',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 10,
            ],
            [
                'key' => 'property.max_images',
                'name_en' => 'Photos per Property',
                'name_am' => 'በአንድ ንብረት የሚፈቀዱ ፎቶዎች',
                'name_om' => 'Suuraalee Qabeenya Tokkoof Hayyamaman',
                'description_en' => 'Maximum number of high-resolution property photos per listing.',
                'category' => 'media',
                'value_type' => FeatureValueType::Integer,
                'default_value' => '8',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 20,
            ],
            [
                'key' => 'property.video_uploads',
                'name_en' => 'Videos per Property',
                'name_am' => 'በአንድ ንብረት የሚፈቀዱ ቪዲዮዎች',
                'name_om' => 'Viidiyoowwan Qabeenya Tokkoof',
                'description_en' => 'Number of video walkthrough uploads or drone presentations allowed per property.',
                'category' => 'media',
                'value_type' => FeatureValueType::Integer,
                'default_value' => '0',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 30,
            ],
            [
                'key' => 'property.virtual_tours',
                'name_en' => '360° Virtual Tours',
                'name_am' => '360° ቨርቹዋል ጉብኝት መፍጠር',
                'name_om' => 'Daawwannaa 360° Virtual Tour',
                'description_en' => 'Upload equirectangular panoramas and publish interactive 360° property walkthroughs.',
                'category' => 'media',
                'value_type' => FeatureValueType::Boolean,
                'default_value' => 'false',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 40,
            ],
            [
                'key' => 'property.room_hotspots',
                'name_en' => 'Room-to-Room Hotspots',
                'name_am' => 'ከክፍል ወደ ክፍል የሚያገናኙ ሆትስፖቶች',
                'name_om' => 'Kutaalee Wal-qabsiisuu (Hotspots)',
                'description_en' => 'Interactive spatial hotspots connecting rooms within virtual tours.',
                'category' => 'media',
                'value_type' => FeatureValueType::Boolean,
                'default_value' => 'false',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 50,
            ],
            [
                'key' => 'property.featured_credits',
                'name_en' => 'Featured Listing Credits',
                'name_am' => 'ተመራጭ የማስታወቂያ ክሬዲቶች',
                'name_om' => 'Kireeditiwwan Beeksisa Filatamaa',
                'description_en' => 'Monthly complimentary credits to promote listings to featured search status.',
                'category' => 'marketing',
                'value_type' => FeatureValueType::Integer,
                'default_value' => '0',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 60,
            ],
            [
                'key' => 'property.analytics',
                'name_en' => 'Property Market Analytics',
                'name_am' => 'የንብረት እና የገበያ ትንታኔዎች',
                'name_om' => 'Xiinxala Gabaa Qabeenyaa',
                'description_en' => 'Access to visitor engagement analytics, price per m² trends, and buyer lead insights.',
                'category' => 'analytics',
                'value_type' => FeatureValueType::Boolean,
                'default_value' => 'false',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 70,
            ],
            [
                'key' => 'property.crm',
                'name_en' => 'Lead Management & CRM',
                'name_am' => 'የደንበኞች ግንኙነት አስተዳደር (CRM)',
                'name_om' => 'Hoggana Qabeenya Maamiltootaa (CRM)',
                'description_en' => 'Pipeline tracking, inquiry assignment, and viewing appointment management.',
                'category' => 'tools',
                'value_type' => FeatureValueType::Boolean,
                'default_value' => 'false',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 80,
            ],
            [
                'key' => 'property.team_members',
                'name_en' => 'Team Members & Sub-agents',
                'name_am' => 'የቡድን አባላት እና ንዑስ ደላሎች',
                'name_om' => 'Miseensota Garee fi Hojjettoota',
                'description_en' => 'Allowed staff seats and agent accounts under the corporate mandate.',
                'category' => 'tools',
                'value_type' => FeatureValueType::Integer,
                'default_value' => '1',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 90,
            ],
            [
                'key' => 'property.advanced_gis',
                'name_en' => 'Advanced PostGIS Neighborhood Heatmap',
                'name_am' => 'የሰፈር ዋጋ በካሬ ሜትር የሚያሳይ ጂአይኤስ ሂትማፕ',
                'name_om' => 'Kaartaa Gabaa Naannoo PostGIS',
                'description_en' => 'Interactive PostGIS neighborhood choropleth heatmap showing asking prices per m².',
                'category' => 'analytics',
                'value_type' => FeatureValueType::Boolean,
                'default_value' => 'false',
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 100,
            ],
        ];

        foreach ($features as $f) {
            SubscriptionFeature::query()->updateOrCreate(
                ['key' => $f['key']],
                $f
            );
        }
    }
}

