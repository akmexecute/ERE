<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EthiopianLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $countryId = $this->upsertLocation(
            parentId: null,
            level: 'country',
            code: 'ETH',
            slug: 'ethiopia',
            nameEn: 'Ethiopia',
            nameAm: 'ኢትዮጵያ',
            nameOm: 'Itoophiyaa',
            lat: 9.1450000,
            lng: 40.4896730,
            ancestry: '/'
        );

        // 1. Addis Ababa Chartered City / Region + All 11 Sub-Cities
        $aaRegionId = $this->upsertLocation($countryId, 'region', 'AA-REG', 'addis-ababa-region', 'Addis Ababa', 'አዲስ አበባ', 'Finfinnee', 9.0191936, 38.7524635, "/{$countryId}/");
        $aaCityId = $this->upsertLocation($aaRegionId, 'city', 'AA', 'addis-ababa', 'Addis Ababa', 'አዲስ አበባ', 'Finfinnee', 9.0191936, 38.7524635, "/{$countryId}/{$aaRegionId}/");

        $subcities = [
            ['code' => 'BOLE', 'en' => 'Bole', 'am' => 'ቦሌ', 'om' => 'Boolee', 'lat' => 8.9894, 'lng' => 38.7890, 'neighborhoods' => [
                ['code' => 'GERJI', 'en' => 'Gerji', 'am' => 'ገርጂ', 'om' => 'Garjii', 'lat' => 8.9965, 'lng' => 38.8095],
                ['code' => 'BOLE-MED', 'en' => 'Bole Medhanialem', 'am' => 'ቦሌ መድኃኔዓለም', 'om' => 'Boolee Medhaaniyaalem', 'lat' => 8.9972, 'lng' => 38.7868],
                ['code' => 'BOLE-BUL', 'en' => 'Bole Bulbula', 'am' => 'ቦሌ ቡልቡላ', 'om' => 'Boolee Bulbulaa', 'lat' => 8.9581, 'lng' => 38.7795],
            ]],
            ['code' => 'YEKA', 'en' => 'Yeka', 'am' => 'የካ', 'om' => 'Yekkaa', 'lat' => 9.0358, 'lng' => 38.8180, 'neighborhoods' => [
                ['code' => 'CMC', 'en' => 'CMC', 'am' => 'ሲኤምሲ', 'om' => 'CMC', 'lat' => 9.0226, 'lng' => 38.8415],
                ['code' => 'MEGENAGNA', 'en' => 'Megenagna', 'am' => 'መገናኛ', 'om' => 'Maganaanyaa', 'lat' => 9.0205, 'lng' => 38.8024],
            ]],
            ['code' => 'LEMI', 'en' => 'Lemi Kura', 'am' => 'ለሚ ኩራ', 'om' => 'Lammii Kuraa', 'lat' => 9.0155, 'lng' => 38.8750, 'neighborhoods' => [
                ['code' => 'AYAT', 'en' => 'Ayat', 'am' => 'አያት', 'om' => 'Ayaat', 'lat' => 9.0185, 'lng' => 38.8792],
                ['code' => 'SUMMIT', 'en' => 'Summit', 'am' => 'ሰሚት', 'om' => 'Samit', 'lat' => 9.0020, 'lng' => 38.8540],
            ]],
            ['code' => 'KIRKOS', 'en' => 'Kirkos', 'am' => 'ቂርቆስ', 'om' => 'Qirqoos', 'lat' => 9.0054, 'lng' => 38.7636, 'neighborhoods' => [
                ['code' => 'KAZANCHIS', 'en' => 'Kazanchis', 'am' => 'ካዛንቺስ', 'om' => 'Kaazaanchiis', 'lat' => 9.0152, 'lng' => 38.7712],
                ['code' => 'MESKEL', 'en' => 'Meskel Flower', 'am' => 'መስቀል ፍላወር', 'om' => 'Masqal Filaawar', 'lat' => 8.9912, 'lng' => 38.7675],
            ]],
            ['code' => 'NIFAS', 'en' => 'Nifas Silk-Lafto', 'am' => 'ንፋስ ስልክ ላፍቶ', 'om' => 'Nifaas Silk Laaftoo', 'lat' => 8.9565, 'lng' => 38.7320, 'neighborhoods' => [
                ['code' => 'SARBET', 'en' => 'Sarbet', 'am' => 'ሳርቤት', 'om' => 'Saarbeet', 'lat' => 8.9922, 'lng' => 38.7372],
                ['code' => 'LEBU', 'en' => 'Lebu', 'am' => 'ለቡ', 'om' => 'Labuu', 'lat' => 8.9430, 'lng' => 38.7120],
            ]],
            ['code' => 'ARADA', 'en' => 'Arada', 'am' => 'አራዳ', 'om' => 'Araadaa', 'lat' => 9.0350, 'lng' => 38.7520, 'neighborhoods' => []],
            ['code' => 'LIDETA', 'en' => 'Lideta', 'am' => 'ልደታ', 'om' => 'Lidataa', 'lat' => 9.0110, 'lng' => 38.7340, 'neighborhoods' => []],
            ['code' => 'KOLFE', 'en' => 'Kolfe Keranio', 'am' => 'ኮልፌ ቀራኒዮ', 'om' => 'Kolfee Qaraaniyoo', 'lat' => 9.0160, 'lng' => 38.6950, 'neighborhoods' => []],
            ['code' => 'GULLELE', 'en' => 'Gullele', 'am' => 'ጉለሌ', 'om' => 'Gullallee', 'lat' => 9.0620, 'lng' => 38.7420, 'neighborhoods' => []],
            ['code' => 'AKAKI', 'en' => 'Akaki Kaliti', 'am' => 'አቃቂ ቃሊቲ', 'om' => 'Aqaaqii Qaallittii', 'lat' => 8.8950, 'lng' => 38.7790, 'neighborhoods' => []],
            ['code' => 'ADDISK', 'en' => 'Addis Ketema', 'am' => 'አዲስ ከተማ', 'om' => 'Addis Katamaa', 'lat' => 9.0310, 'lng' => 38.7260, 'neighborhoods' => []],
        ];

        foreach ($subcities as $sc) {
            $scId = $this->upsertLocation(
                $aaCityId,
                'subcity',
                $sc['code'],
                'addis-ababa-'.Str::slug($sc['en']),
                $sc['en'],
                $sc['am'],
                $sc['om'],
                $sc['lat'],
                $sc['lng'],
                "/{$countryId}/{$aaRegionId}/{$aaCityId}/"
            );

            foreach ($sc['neighborhoods'] as $nb) {
                $this->upsertLocation(
                    $scId,
                    'neighborhood',
                    $nb['code'],
                    'addis-ababa-'.Str::slug($sc['en'].'-'.$nb['en']),
                    $nb['en'],
                    $nb['am'],
                    $nb['om'],
                    $nb['lat'],
                    $nb['lng'],
                    "/{$countryId}/{$aaRegionId}/{$aaCityId}/{$scId}/"
                );
            }
        }

        // 2. Major National Regions & Cities (SRS Sections 176 & 215)
        $regionalCities = [
            ['reg_code' => 'HAR', 'reg_en' => 'Harari Region', 'reg_am' => 'ሐረሪ ክልል', 'reg_om' => 'Naannoo Hararii', 'city_code' => 'HRR', 'city_en' => 'Harar', 'city_am' => 'ሐረር', 'city_om' => 'Harar', 'lat' => 9.3139, 'lng' => 42.1182],
            ['reg_code' => 'SOM', 'reg_en' => 'Somali Region', 'reg_am' => 'ሶማሌ ክልል', 'reg_om' => 'Naannoo Somaalee', 'city_code' => 'JJG', 'city_en' => 'Jijiga', 'city_am' => 'ጅጅጋ', 'city_om' => 'Jigjiga', 'lat' => 9.3541, 'lng' => 42.7956],
            ['reg_code' => 'DD-REG', 'reg_en' => 'Dire Dawa Administration', 'reg_am' => 'ድሬዳዋ አስተዳደር', 'reg_om' => 'Bulchiinsa Dirree Dhawaa', 'city_code' => 'DDW', 'city_en' => 'Dire Dawa', 'city_am' => 'ድሬዳዋ', 'city_om' => 'Dirree Dhawaa', 'lat' => 9.6009, 'lng' => 41.8501],
            ['reg_code' => 'SID', 'reg_en' => 'Sidama Region', 'reg_am' => 'ሲዳማ ክልል', 'reg_om' => 'Naannoo Sidaamaa', 'city_code' => 'HWS', 'city_en' => 'Hawassa', 'city_am' => 'ሀዋሳ', 'city_om' => 'Hawaasaa', 'lat' => 7.0504, 'lng' => 38.4955],
            ['reg_code' => 'AMH', 'reg_en' => 'Amhara Region', 'reg_am' => 'አማራ ክልል', 'reg_om' => 'Naannoo Amaaraa', 'city_code' => 'BJR', 'city_en' => 'Bahir Dar', 'city_am' => 'ባሕር ዳር', 'city_om' => 'Baahir Daar', 'lat' => 11.5742, 'lng' => 37.3614],
            ['reg_code' => 'ORO', 'reg_en' => 'Oromia Region', 'reg_am' => 'ኦሮሚያ ክልል', 'reg_om' => 'Naannoo Oromiyaa', 'city_code' => 'ADM', 'city_en' => 'Adama', 'city_am' => 'አዳማ', 'city_om' => 'Adaamaa', 'lat' => 8.5414, 'lng' => 39.2689],
            ['reg_code' => 'TIG', 'reg_en' => 'Tigray Region', 'reg_am' => 'ትግራይ ክልል', 'reg_om' => 'Naannoo Tigraay', 'city_code' => 'MQX', 'city_en' => 'Mekelle', 'city_am' => 'መቐለ', 'city_om' => 'Maqalee', 'lat' => 13.4967, 'lng' => 39.4753],
        ];

        foreach ($regionalCities as $rc) {
            $regId = $this->upsertLocation($countryId, 'region', $rc['reg_code'], Str::slug($rc['reg_en']), $rc['reg_en'], $rc['reg_am'], $rc['reg_om'], $rc['lat'], $rc['lng'], "/{$countryId}/");
            $this->upsertLocation($regId, 'city', $rc['city_code'], Str::slug($rc['city_en']), $rc['city_en'], $rc['city_am'], $rc['city_om'], $rc['lat'], $rc['lng'], "/{$countryId}/{$regId}/");
        }
    }

    private function upsertLocation(
        ?int $parentId,
        string $level,
        string $code,
        string $slug,
        string $nameEn,
        string $nameAm,
        string $nameOm,
        float $lat,
        float $lng,
        string $ancestry
    ): int {
        DB::table('locations')->updateOrInsert(
            ['slug' => $slug],
            [
                'parent_id' => $parentId,
                'level' => $level,
                'code' => $code,
                'ancestry_path' => $ancestry,
                'name_en' => $nameEn,
                'name_am' => $nameAm,
                'name_om' => $nameOm,
                'latitude' => $lat,
                'longitude' => $lng,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $id = (int) DB::table('locations')->where('slug', $slug)->value('id');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE locations SET center_point = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [$lng, $lat, $id]
            );
        }

        return $id;
    }
}
