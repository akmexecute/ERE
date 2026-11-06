<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Actions;

use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class ParseNaturalPropertyQueryAction
{
    /**
     * @return array{
     *   transaction_mode: ?string,
     *   property_type_slug: ?string,
     *   category_code: ?string,
     *   location_keyword: ?string,
     *   min_bedrooms: ?int,
     *   max_price_etb: ?float,
     *   min_price_etb: ?float,
     *   amenities: array<int, string>
     * }
     */
    public function execute(string $rawQuery): array
    {
        $q = mb_strtolower(trim($rawQuery));

        $result = [
            'transaction_mode' => null,
            'property_type_slug' => null,
            'category_code' => null,
            'location_keyword' => null,
            'min_bedrooms' => null,
            'max_price_etb' => null,
            'min_price_etb' => null,
            'amenities' => [],
        ];

        if ($q === '') {
            return $result;
        }

        // 1. Transaction Mode (BUY / RENT / LEASE)
        if (preg_match('/(?:\b|[\s,፡።]|^)(for rent|rent|rental|to rent|ለኪራይ|ኪራይ|kireeffachuuf)(?:[\s,፡።]|\b|$)/ui', $q)) {
            $result['transaction_mode'] = 'RENT';
        } elseif (preg_match('/(?:\b|[\s,፡።]|^)(lease|for lease|to lease|ለሊዝ|ሊዝ)(?:[\s,፡።]|\b|$)/ui', $q)) {
            $result['transaction_mode'] = 'LEASE';
        } elseif (preg_match('/(?:\b|[\s,፡።]|^)(buy|for sale|sale|to buy|ለሽያጭ|ሽያጭ|bituu|gurguraa)(?:[\s,፡።]|\b|$)/ui', $q)) {
            $result['transaction_mode'] = 'BUY';
        }

        // 2. Bedrooms: e.g. "4-bedroom", "4 bedrooms", "4 bed", "4br", "4 መኝታ"
        if (preg_match('/(\d+)\s*(?:-\s*)?(?:bedrooms?|beds?|br|መኝታ|ciisisa)\b/ui', $q, $m)) {
            $result['min_bedrooms'] = (int) $m[1];
        }

        // 3. Dynamic Property Type & Category Resolution
        $this->resolvePropertyTypeAndCategory($q, $result);

        // 4. Price Floor (Min Price ETB): e.g. "above 10m", "over 20 million", "from 5 million", "min 100k", ">= 10,000,000", "ከ 10 ሚሊዮን በላይ"
        if (preg_match('/(?:above|over|more than|from|min|>=?)\s*(?:etb\s*)?([\d,]+(?:\.\d+)?)\s*(million|m|thousand|k|billion|b|ሚሊዮን|ሺህ|ሺ)?/ui', $q, $pMin)) {
            $result['min_price_etb'] = $this->calculatePriceEtb($pMin[1], $pMin[2] ?? '');
        } elseif (preg_match('/(?:ከ\s*)?([\d,]+(?:\.\d+)?)\s*(ሚሊዮን|ሺህ|ሺ|m|k)?\s*(?:በላይ|olka)/ui', $q, $pMinAm)) {
            $result['min_price_etb'] = $this->calculatePriceEtb($pMinAm[1], $pMinAm[2] ?? '');
        }

        // 5. Price Ceiling (Max Price ETB): e.g. "under 30 million", "below 15m", "up to 80k", "max 25m", "<= 25,000,000", "እስከ 30 ሚሊዮን", "ከ 30 ሚሊዮን በታች"
        if (preg_match('/(?:under|below|less than|max|up to|<=?)\s*(?:etb\s*)?([\d,]+(?:\.\d+)?)\s*(million|m|thousand|k|billion|b|ሚሊዮን|ሺህ|ሺ)?/ui', $q, $pMax)) {
            $result['max_price_etb'] = $this->calculatePriceEtb($pMax[1], $pMax[2] ?? '');
        } elseif (preg_match('/(?:እስከ|hamma)\s*(?:etb\s*)?([\d,]+(?:\.\d+)?)\s*(million|m|thousand|k|ሚሊዮን|ሺህ|ሺ)?/ui', $q, $pMaxAm)) {
            $result['max_price_etb'] = $this->calculatePriceEtb($pMaxAm[1], $pMaxAm[2] ?? '');
        } elseif (preg_match('/(?:ከ\s*)?([\d,]+(?:\.\d+)?)\s*(ሚሊዮን|ሺህ|ሺ|m|k)?\s*(?:በታች|gadi)/ui', $q, $pMaxAm2)) {
            $result['max_price_etb'] = $this->calculatePriceEtb($pMaxAm2[1], $pMaxAm2[2] ?? '');
        }

        // 6. Dynamic Location Resolution
        $this->resolveLocation($q, $result);

        // 7. Dynamic Amenities Resolution
        $this->resolveAmenities($q, $result);

        return $result;
    }

    private function calculatePriceEtb(string $rawNumber, string $unit): float
    {
        $num = (float) str_replace(',', '', $rawNumber);
        $normUnit = mb_strtolower(trim($unit));

        $multiplier = match ($normUnit) {
            'billion', 'b', 'ቢሊዮን' => 1_000_000_000.0,
            'million', 'm', 'ሚሊዮን', 'miliyoona' => 1_000_000.0,
            'thousand', 'k', 'ሺህ', 'ሺ', 'kuma' => 1_000.0,
            default => 1.0,
        };

        return $num * $multiplier;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resolvePropertyTypeAndCategory(string $q, array &$result): void
    {
        $typeCatalog = $this->getPropertyTypeCatalog();

        foreach ($typeCatalog as $slug => $keywords) {
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if ($kw === '') {
                    continue;
                }

                // Match with word boundary or spaces/Ethiopic word separators
                $pattern = '/(?:\b|[\s,፡።]|^)' . preg_quote($kw, '/') . '(?:\b|[\s,፡።]|$)/ui';
                if (preg_match($pattern, $q)) {
                    $result['property_type_slug'] = $slug;

                    // Auto-infer default transaction mode if not specified
                    if ($result['transaction_mode'] === null && in_array($slug, ['villa', 'residential-land', 'condominium'], true)) {
                        $result['transaction_mode'] = 'BUY';
                    }

                    return;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resolveLocation(string $q, array &$result): void
    {
        $locationCatalog = $this->getLocationCatalog();

        // Sort by length descending so longer phrases ("Addis Ababa", "Lemi Kura") match before ("Kura", "Addis")
        uasort($locationCatalog, fn ($a, $b) => mb_strlen($b['keyword']) <=> mb_strlen($a['keyword']));

        foreach ($locationCatalog as $loc) {
            $kw = $loc['keyword'];
            $pattern = '/(?:\b|[\s,፡።]|^)(?:በ)?' . preg_quote($kw, '/') . '(?:\b|[\s,፡።]|$)/ui';
            if (preg_match($pattern, $q)) {
                $result['location_keyword'] = $loc['slug'];
                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resolveAmenities(string $q, array &$result): void
    {
        $amenityCatalog = $this->getAmenityCatalog();

        foreach ($amenityCatalog as $slug => $keywords) {
            foreach ($keywords as $kw) {
                $kw = trim($kw);
                if ($kw === '') {
                    continue;
                }

                $pattern = '/(?:\b|[\s,፡።]|^)' . preg_quote($kw, '/') . '(?:\b|[\s,፡።]|$)/ui';
                if (preg_match($pattern, $q)) {
                    $result['amenities'][] = $slug;
                    break;
                }
            }
        }

        $result['amenities'] = array_values(array_unique($result['amenities']));
    }

    /**
     * @return array<string, list<string>>
     */
    private function getPropertyTypeCatalog(): array
    {
        // Standard baseline mappings
        $baseline = [
            'villa' => ['villa', 'villas', 'ቪላ'],
            'apartment' => ['apartment', 'apartments', 'flat', 'አፓርታማ'],
            'condominium' => ['condominium', 'condo', 'ኮንዶሚኒየም'],
            'house' => ['house', 'houses', 'መኖሪያ ቤት'],
            'office' => ['office', 'offices', 'ቢሮ'],
            'warehouse' => ['warehouse', 'መጋዘን'],
            'shop-retail' => ['shop', 'retail', 'ሱቅ'],
            'residential-land' => ['land', 'plot', 'መሬት', 'ቦታ'],
            'guesthouse' => ['guesthouse', 'እንግዳ ማረፊያ'],
        ];

        try {
            if (class_exists(PropertyType::class)) {
                $types = PropertyType::query()->where('is_active', true)->get(['slug', 'name_en', 'name_am', 'name_om']);
                foreach ($types as $type) {
                    $slug = $type->slug;
                    if (! isset($baseline[$slug])) {
                        $baseline[$slug] = [];
                    }
                    if ($type->name_en) {
                        $baseline[$slug][] = mb_strtolower($type->name_en);
                        $baseline[$slug][] = mb_strtolower($type->name_en) . 's';
                    }
                    if ($type->name_am) {
                        $baseline[$slug][] = mb_strtolower($type->name_am);
                    }
                    if ($type->name_om) {
                        $baseline[$slug][] = mb_strtolower($type->name_om);
                    }
                }
            }
        } catch (Throwable) {
            // Database not reachable, use baseline
        }

        return $baseline;
    }

    /**
     * @return list<array{slug: string, keyword: string}>
     */
    private function getLocationCatalog(): array
    {
        $baseline = [
            ['slug' => 'bole', 'keyword' => 'bole'],
            ['slug' => 'bole', 'keyword' => 'ቦሌ'],
            ['slug' => 'kazanchis', 'keyword' => 'kazanchis'],
            ['slug' => 'kazanchis', 'keyword' => 'ካዛንቺስ'],
            ['slug' => 'gerji', 'keyword' => 'gerji'],
            ['slug' => 'gerji', 'keyword' => 'ገርጂ'],
            ['slug' => 'cmc', 'keyword' => 'cmc'],
            ['slug' => 'ayat', 'keyword' => 'ayat'],
            ['slug' => 'ayat', 'keyword' => 'አያት'],
            ['slug' => 'summit', 'keyword' => 'summit'],
            ['slug' => 'sarbet', 'keyword' => 'sarbet'],
            ['slug' => 'sarbet', 'keyword' => 'ሳርቤት'],
            ['slug' => 'lebu', 'keyword' => 'lebu'],
            ['slug' => 'lebu', 'keyword' => 'ለቡ'],
            ['slug' => 'megenagna', 'keyword' => 'megenagna'],
            ['slug' => 'megenagna', 'keyword' => 'መገናኛ'],
            ['slug' => 'yeka', 'keyword' => 'yeka'],
            ['slug' => 'yeka', 'keyword' => 'የካ'],
            ['slug' => 'kirkos', 'keyword' => 'kirkos'],
            ['slug' => 'kirkos', 'keyword' => 'ቂርቆስ'],
            ['slug' => 'lemi-kura', 'keyword' => 'lemi kura'],
            ['slug' => 'lemi-kura', 'keyword' => 'ለሚ ኩራ'],
            ['slug' => 'arada', 'keyword' => 'arada'],
            ['slug' => 'arada', 'keyword' => 'አራዳ'],
            ['slug' => 'lideta', 'keyword' => 'lideta'],
            ['slug' => 'lideta', 'keyword' => 'ልደታ'],
            ['slug' => 'kolfe', 'keyword' => 'kolfe'],
            ['slug' => 'kolfe', 'keyword' => 'ኮልፌ'],
            ['slug' => 'gullele', 'keyword' => 'gullele'],
            ['slug' => 'gullele', 'keyword' => 'ጉለሌ'],
            ['slug' => 'akaki', 'keyword' => 'akaki'],
            ['slug' => 'akaki', 'keyword' => 'አቃቂ'],
            ['slug' => 'addis-ababa', 'keyword' => 'addis ababa'],
            ['slug' => 'addis-ababa', 'keyword' => 'አዲስ አበባ'],
            ['slug' => 'hawassa', 'keyword' => 'hawassa'],
            ['slug' => 'bahir-dar', 'keyword' => 'bahir dar'],
            ['slug' => 'adama', 'keyword' => 'adama'],
            ['slug' => 'bishoftu', 'keyword' => 'bishoftu'],
            ['slug' => 'dire-dawa', 'keyword' => 'dire dawa'],
            ['slug' => 'mekelle', 'keyword' => 'mekelle'],
            ['slug' => 'harar', 'keyword' => 'harar'],
            ['slug' => 'jijiga', 'keyword' => 'jijiga'],
        ];

        try {
            if (class_exists(Location::class)) {
                $locations = Location::query()
                    ->where('is_active', true)
                    ->whereIn('level', ['city', 'subcity', 'woreda', 'kebele', 'neighborhood'])
                    ->take(200)
                    ->get(['slug', 'name_en', 'name_am', 'name_om']);

                foreach ($locations as $loc) {
                    if ($loc->name_en) {
                        $baseline[] = ['slug' => $loc->slug, 'keyword' => mb_strtolower($loc->name_en)];
                    }
                    if ($loc->name_am) {
                        $baseline[] = ['slug' => $loc->slug, 'keyword' => mb_strtolower($loc->name_am)];
                    }
                    if ($loc->name_om) {
                        $baseline[] = ['slug' => $loc->slug, 'keyword' => mb_strtolower($loc->name_om)];
                    }
                }
            }
        } catch (Throwable) {
            // Database not reachable, use baseline
        }

        return $baseline;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getAmenityCatalog(): array
    {
        $baseline = [
            'backup-generator' => ['generator', 'ጀነሬተር', 'jenereetara'],
            'parking' => ['parking', 'ፓርኪንግ', 'paarkingii'],
            'wifi' => ['wifi', 'wi-fi', 'internet', 'ኢንተርኔት'],
            'garden' => ['garden', 'አትክልት', 'iddoo biqiltuu'],
            'swimming-pool' => ['pool', 'swimming pool', 'ዋና ገንዳ'],
            'elevator' => ['elevator', 'lift', 'ሊፍት'],
            'furnished' => ['furnished', 'የተሟላ እቃ'],
        ];

        try {
            if (class_exists(PropertyAmenity::class)) {
                $amenities = PropertyAmenity::query()->where('is_active', true)->get(['slug', 'name_en', 'name_am', 'name_om']);
                foreach ($amenities as $amenity) {
                    $slug = $amenity->slug;
                    if (! isset($baseline[$slug])) {
                        $baseline[$slug] = [];
                    }
                    if ($amenity->name_en) {
                        $baseline[$slug][] = mb_strtolower($amenity->name_en);
                    }
                    if ($amenity->name_am) {
                        $baseline[$slug][] = mb_strtolower($amenity->name_am);
                    }
                    if ($amenity->name_om) {
                        $baseline[$slug][] = mb_strtolower($amenity->name_om);
                    }
                }
            }
        } catch (Throwable) {
            // Database not reachable, use baseline
        }

        return $baseline;
    }
}
