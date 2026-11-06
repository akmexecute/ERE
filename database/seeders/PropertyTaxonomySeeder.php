<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PropertyTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'RES', 'en' => 'Residential', 'am' => 'መኖሪያ ቤት', 'om' => 'Mana Jireenyaa', 'types' => [
                ['code' => 'VILLA', 'en' => 'Villa', 'am' => 'ቪላ', 'om' => 'Viilaa', 'basis' => 'land_area', 'beds' => true],
                ['code' => 'HOUSE', 'en' => 'House', 'am' => 'መኖሪያ ቤት', 'om' => 'Mana', 'basis' => 'land_area', 'beds' => true],
                ['code' => 'APT', 'en' => 'Apartment', 'am' => 'አፓርታማ', 'om' => 'Apaartaamaa', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'CONDO', 'en' => 'Condominium', 'am' => 'ኮንዶሚኒየም', 'om' => 'Kondominiyeemii', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'TOWNHOUSE', 'en' => 'Townhouse', 'am' => 'ታውን ሃውስ', 'om' => 'Taawun Haawus', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'PENTHOUSE', 'en' => 'Penthouse', 'am' => 'ፔንት ሃውስ', 'om' => 'Peent Haawus', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'STUDIO', 'en' => 'Studio', 'am' => 'ስቱዲዮ', 'om' => 'Istuudiyoo', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'COMPOUND', 'en' => 'Residential Compound', 'am' => 'የመኖሪያ ግቢ', 'om' => 'Mooraa Jireenyaa', 'basis' => 'land_area', 'beds' => true],
            ]],
            ['code' => 'LND', 'en' => 'Land', 'am' => 'መሬት / ቦታ', 'om' => 'Lafa', 'types' => [
                ['code' => 'RES_LAND', 'en' => 'Residential Land', 'am' => 'የመኖሪያ ቦታ', 'om' => 'Lafa Mana Jireenyaa', 'basis' => 'land_area', 'beds' => false],
                ['code' => 'COM_LAND', 'en' => 'Commercial Land', 'am' => 'የንግድ ቦታ', 'om' => 'Lafa Daldalaa', 'basis' => 'land_area', 'beds' => false],
                ['code' => 'IND_LAND', 'en' => 'Industrial Land', 'am' => 'የኢንዱስትሪ ቦታ', 'om' => 'Lafa Industirii', 'basis' => 'land_area', 'beds' => false],
                ['code' => 'AGR_LAND', 'en' => 'Agricultural Land', 'am' => 'የእርሻ መሬት', 'om' => 'Lafa Qonnaa', 'basis' => 'land_area', 'beds' => false],
                ['code' => 'DEV_LAND', 'en' => 'Development Land', 'am' => 'የልማት መሬት', 'om' => 'Lafa Misoomaa', 'basis' => 'land_area', 'beds' => false],
            ]],
            ['code' => 'COM', 'en' => 'Commercial', 'am' => 'የንግድ ንብረት', 'om' => 'Qabeenya Daldalaa', 'types' => [
                ['code' => 'OFFICE', 'en' => 'Office', 'am' => 'ቢሮ', 'om' => 'Biiroo', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'SHOP', 'en' => 'Shop / Retail', 'am' => 'ሱቅ', 'om' => 'Suuqii', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'COM_BLDG', 'en' => 'Commercial Building', 'am' => 'የንግድ ህንፃ', 'om' => 'Gamoo Daldalaa', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'WAREHOUSE', 'en' => 'Warehouse', 'am' => 'መጋዘን', 'om' => 'Magaazana', 'basis' => 'building_area', 'beds' => false],
            ]],
            ['code' => 'IND', 'en' => 'Industrial', 'am' => 'ኢንዱስትሪ', 'om' => 'Industirii', 'types' => [
                ['code' => 'FACTORY', 'en' => 'Factory', 'am' => 'ፋብሪካ', 'om' => 'Faabrikaa', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'STORAGE', 'en' => 'Storage Facility', 'am' => 'ማከማቻ', 'om' => 'Kuusaa', 'basis' => 'building_area', 'beds' => false],
            ]],
            ['code' => 'HSP', 'en' => 'Hospitality', 'am' => 'እንግዳ ተቀባይነት', 'om' => 'Keessummeessuu', 'types' => [
                ['code' => 'GUESTHOUSE', 'en' => 'Guesthouse', 'am' => 'እንግዳ ማረፊያ', 'om' => 'Mana Keessummaa', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'SERV_APT', 'en' => 'Serviced Apartment', 'am' => 'ሰርቪስድ አፓርታማ', 'om' => 'Apaartaamaa Tajaajilaa', 'basis' => 'building_area', 'beds' => true],
                ['code' => 'HOTEL', 'en' => 'Hotel', 'am' => 'ሆቴል', 'om' => 'Hoteela', 'basis' => 'building_area', 'beds' => true],
            ]],
            ['code' => 'SPC', 'en' => 'Special Purpose', 'am' => 'ልዩ አገልግሎት', 'om' => 'Tajaajila Addaa', 'types' => [
                ['code' => 'CLINIC', 'en' => 'Clinic / Health Facility', 'am' => 'ክሊኒክ / የጤና ተቋም', 'om' => 'Kilinika / Dhaabbata Fayyaa', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'SCHOOL', 'en' => 'School / Educational', 'am' => 'ትምህርት ቤት', 'om' => 'Mana Barumsaa', 'basis' => 'building_area', 'beds' => false],
                ['code' => 'FUEL_STATION', 'en' => 'Fuel Station', 'am' => 'የነዳጅ ማደያ', 'om' => 'Buufata Bobaʼaa', 'basis' => 'land_area', 'beds' => false],
                ['code' => 'DIPLOMATIC', 'en' => 'Embassy / Diplomatic', 'am' => 'ኤምባሲ / ዲፕሎማቲክ', 'om' => 'Embaasii / Diippilomaatikii', 'basis' => 'land_area', 'beds' => false],
            ]],
        ];

        foreach ($categories as $idx => $cat) {
            $existingCat = DB::table('property_categories')->where('code', $cat['code'])->first();
            if (! $existingCat) {
                DB::table('property_categories')->insert([
                    'code' => $cat['code'],
                    'slug' => Str::slug($cat['en']),
                    'name_en' => $cat['en'],
                    'name_am' => $cat['am'],
                    'name_om' => $cat['om'],
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Preserve admin customizations, only ensure slug exists
                if (empty($existingCat->slug)) {
                    DB::table('property_categories')->where('id', $existingCat->id)->update([
                        'slug' => Str::slug($cat['en']),
                        'updated_at' => now(),
                    ]);
                }
            }

            $categoryId = (int) DB::table('property_categories')->where('code', $cat['code'])->value('id');

            foreach ($cat['types'] as $tIdx => $type) {
                $existingType = DB::table('property_types')->where('code', $type['code'])->first();
                if (! $existingType) {
                    DB::table('property_types')->insert([
                        'category_id' => $categoryId,
                        'code' => $type['code'],
                        'slug' => Str::slug($type['en']),
                        'name_en' => $type['en'],
                        'name_am' => $type['am'],
                        'name_om' => $type['om'],
                        'area_basis' => $type['basis'],
                        'requires_bedrooms' => $type['beds'],
                        'requires_floors' => $cat['code'] !== 'LND',
                        'is_active' => true,
                        'sort_order' => $tIdx + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Preserve admin changes (e.g. disabled is_active, custom sort_order)
                    $updates = [];
                    if (empty($existingType->slug)) {
                        $updates['slug'] = Str::slug($type['en']);
                    }
                    if (empty($existingType->area_basis)) {
                        $updates['area_basis'] = $type['basis'];
                    }
                    if (! empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('property_types')->where('id', $existingType->id)->update($updates);
                    }
                }
            }
        }

        // Listing Types (SRS Sections 7 & 8)
        $listingTypes = [
            ['code' => 'FOR_SALE', 'mode' => 'BUY', 'en' => 'For Sale', 'am' => 'ለሽያጭ', 'om' => 'Gurguraaf', 'period' => 'total', 'phase1' => true],
            ['code' => 'FOR_RENT', 'mode' => 'RENT', 'en' => 'For Rent', 'am' => 'ለኪራይ', 'om' => 'Kiraaf', 'period' => 'per_month', 'phase1' => true],
            ['code' => 'FOR_LEASE', 'mode' => 'LEASE', 'en' => 'For Lease', 'am' => 'ለሊዝ', 'om' => 'Liiziif', 'period' => 'per_year', 'phase1' => true],
            ['code' => 'INVESTMENT', 'mode' => 'BUY', 'en' => 'Investment Opportunity', 'am' => 'የኢንቨስትመንት እድል', 'om' => 'Carraa Investimentii', 'period' => 'total', 'phase1' => true],
            ['code' => 'SHORT_STAY', 'mode' => 'STAY', 'en' => 'Short Stay', 'am' => 'አጭር ቆይታ', 'om' => 'Turtii Gabaabaa', 'period' => 'per_night', 'phase1' => false],
            ['code' => 'NEW_DEV', 'mode' => 'NEW_PROJECTS', 'en' => 'New Development', 'am' => 'አዲስ ፕሮጀክት', 'om' => 'Pirojektii Haaraa', 'period' => 'total', 'phase1' => false],
        ];

        foreach ($listingTypes as $idx => $lt) {
            $existingLt = DB::table('listing_types')->where('code', $lt['code'])->first();
            if (! $existingLt) {
                DB::table('listing_types')->insert([
                    'code' => $lt['code'],
                    'slug' => Str::slug($lt['en']),
                    'transaction_mode' => $lt['mode'],
                    'name_en' => $lt['en'],
                    'name_am' => $lt['am'],
                    'name_om' => $lt['om'],
                    'default_price_period' => $lt['period'],
                    'is_phase1_enabled' => $lt['phase1'],
                    'sort_order' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Amenities (SRS Section 16 & 44)
        $amenities = [
            ['group' => 'internet', 'slug' => 'wifi', 'en' => 'Wi-Fi / Broadband', 'am' => 'ዋይፋይ ኢንተርኔት', 'om' => 'Wi-Fi'],
            ['group' => 'internet', 'slug' => 'fiber', 'en' => 'Ethio Telecom Fiber', 'am' => 'ፋይበር ኢንተርኔት', 'om' => 'Faayibar'],
            ['group' => 'utilities', 'slug' => 'backup-generator', 'en' => 'Backup Generator', 'am' => 'ጀነሬተር', 'om' => 'Jeneraatara'],
            ['group' => 'utilities', 'slug' => 'solar-power', 'en' => 'Solar Power / Inverter', 'am' => 'ሶላር ኃይል', 'om' => 'Humna Aduu'],
            ['group' => 'utilities', 'slug' => 'water-tank', 'en' => 'Reserve Water Tanker', 'am' => 'የውሃ ታንከር', 'om' => 'Taankarii Bishaanii'],
            ['group' => 'security', 'slug' => '24h-security', 'en' => '24/7 Compound Security', 'am' => 'የ24 ሰዓት ጥበቃ', 'om' => 'Eegumsa Saʼaatii 24'],
            ['group' => 'security', 'slug' => 'cctv', 'en' => 'CCTV Cameras', 'am' => 'ሲሲቲቪ ካሜራ', 'om' => 'Kaameraa CCTV'],
            ['group' => 'building', 'slug' => 'elevator', 'en' => 'Elevator / Lift', 'am' => 'ሊፍት', 'om' => 'Liiftii'],
            ['group' => 'building', 'slug' => 'furnished', 'en' => 'Fully Furnished', 'am' => 'ሙሉ እቃ ያለው', 'om' => 'Meeshaa Guutuu Qabu'],
            ['group' => 'outdoor', 'slug' => 'garden', 'en' => 'Private Garden', 'am' => 'አትክልት ቦታ', 'om' => 'Iddoo Biqiltuu'],
            ['group' => 'outdoor', 'slug' => 'balcony', 'en' => 'Balcony / Terrace', 'am' => 'በረንዳ', 'om' => 'Barandaa'],
            ['group' => 'outdoor', 'slug' => 'swimming-pool', 'en' => 'Swimming Pool', 'am' => 'መዋኛ ገንዳ', 'om' => 'Haroo Daakaa'],
        ];

        foreach ($amenities as $idx => $am) {
            $existingAm = DB::table('property_amenities')->where('slug', $am['slug'])->first();
            if (! $existingAm) {
                DB::table('property_amenities')->insert([
                    'slug' => $am['slug'],
                    'group_key' => $am['group'],
                    'name_en' => $am['en'],
                    'name_am' => $am['am'],
                    'name_om' => $am['om'],
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Dynamic Property Features (SRS Section 44)
        $features = [
            [
                'slug' => 'zoning_type',
                'en' => 'Zoning Type',
                'am' => 'የዞን ክፍፍል አይነት',
                'om' => 'Gosa Zoonii',
                'input_type' => 'select',
                'options' => ['residential', 'commercial', 'mixed_use', 'industrial', 'agricultural'],
                'unit' => null,
                'categories' => ['LND', 'COM', 'IND'],
            ],
            [
                'slug' => 'road_access_width_m',
                'en' => 'Road Access Width',
                'am' => 'የመንገድ ስፋት',
                'om' => "Bal'ina Daandii",
                'input_type' => 'number',
                'options' => null,
                'unit' => 'm',
                'categories' => ['RES', 'LND', 'COM', 'IND'],
            ],
            [
                'slug' => 'power_phase_kva',
                'en' => 'Power Capacity',
                'am' => 'የኤሌክትሪክ ኃይል መጠን',
                'om' => 'Humni Humna Ibsaa',
                'input_type' => 'number',
                'options' => null,
                'unit' => 'kVA',
                'categories' => ['COM', 'IND'],
            ],
            [
                'slug' => 'topography',
                'en' => 'Land Topography',
                'am' => 'የመሬት ገጽታ',
                'om' => 'Boca Lafa',
                'input_type' => 'select',
                'options' => ['flat', 'gentle_slope', 'steep_slope'],
                'unit' => null,
                'categories' => ['LND'],
            ],
            [
                'slug' => 'soil_type',
                'en' => 'Soil Classification',
                'am' => 'የአፈር አይነት',
                'om' => 'Gosa Biyyoo',
                'input_type' => 'select',
                'options' => ['red_soil', 'black_cotton', 'clay', 'sandy'],
                'unit' => null,
                'categories' => ['LND'],
            ],
        ];

        foreach ($features as $idx => $feat) {
            $existingFeat = DB::table('property_features')->where('slug', $feat['slug'])->first();
            if (! $existingFeat) {
                DB::table('property_features')->insert([
                    'slug' => $feat['slug'],
                    'name_en' => $feat['en'],
                    'name_am' => $feat['am'],
                    'name_om' => $feat['om'],
                    'input_type' => $feat['input_type'],
                    'options_json' => $feat['options'] ? json_encode($feat['options'], JSON_THROW_ON_ERROR) : null,
                    'unit' => $feat['unit'],
                    'applicable_category_codes' => json_encode($feat['categories'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
