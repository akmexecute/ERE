<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Actions;

final readonly class ParseNaturalPropertyQueryAction
{
    /**
     * @return array{
     *   transaction_mode: ?string,
     *   property_type_slug: ?string,
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
        if (preg_match('/\b(for rent|rent|rental|ለኪራይ|kireeffachuuf)\b/u', $q)) {
            $result['transaction_mode'] = 'RENT';
        } elseif (preg_match('/\b(lease|for lease|ለሊዝ)\b/u', $q)) {
            $result['transaction_mode'] = 'LEASE';
        } elseif (preg_match('/\b(buy|for sale|sale|ለሽያጭ)\b/u', $q)) {
            $result['transaction_mode'] = 'BUY';
        }

        // 2. Bedrooms: e.g. "4-bedroom", "4 bedrooms", "4 bed", "4br"
        if (preg_match('/\b(\d+)\s*(?:-\s*)?(?:bedrooms?|beds?|br)\b/u', $q, $m)) {
            $result['min_bedrooms'] = (int) $m[1];
        }

        // 3. Property Type detection
        $typeMap = [
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

        foreach ($typeMap as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/\b'.preg_quote($kw, '/').'\b/u', $q)) {
                    $result['property_type_slug'] = $slug;
                    if ($result['transaction_mode'] === null && in_array($slug, ['villa', 'residential-land', 'condominium'], true)) {
                        $result['transaction_mode'] = 'BUY';
                    }
                    break 2;
                }
            }
        }

        // 4. Price ceiling: e.g. "under 30 million", "below 15m", "under 80k", "< 25,000,000"
        if (preg_match('/(?:under|below|less than|max|<=?)\s*(?:etb\s*)?([\d,]+(?:\.\d+)?)\s*(million|m|thousand|k)?\b/u', $q, $pm)) {
            $num = (float) str_replace(',', '', $pm[1]);
            $unit = $pm[2] ?? '';
            $multiplier = match ($unit) {
                'million', 'm' => 1_000_000.0,
                'thousand', 'k' => 1_000.0,
                default => 1.0,
            };
            $result['max_price_etb'] = $num * $multiplier;
        }

        // 5. Known Ethiopian Cities, Sub-cities & Neighborhoods
        $knownLocations = [
            'bole', 'gerji', 'cmc', 'ayat', 'summit', 'kazanchis', 'sarbet', 'lebu',
            'megenagna', 'yeka', 'kirkos', 'lemi kura', 'arada', 'lideta', 'kolfe',
            'gullele', 'akaki', 'addis ababa', 'harar', 'jijiga', 'dire dawa',
            'hawassa', 'bahir dar', 'adama', 'bishoftu', 'mekelle',
        ];
        foreach ($knownLocations as $loc) {
            if (preg_match('/\b'.preg_quote($loc, '/').'\b/u', $q)) {
                $result['location_keyword'] = $loc;
                break;
            }
        }

        // 6. Amenities
        $amenityKeywords = [
            'backup-generator' => ['generator', 'ጀነሬተር'],
            'parking' => ['parking', 'ፓርኪንግ'],
            'wifi' => ['wifi', 'wi-fi', 'internet'],
            'garden' => ['garden', 'አትክልት'],
            'swimming-pool' => ['pool', 'swimming pool'],
            'elevator' => ['elevator', 'lift', 'ሊፍት'],
            'furnished' => ['furnished'],
        ];

        foreach ($amenityKeywords as $slug => $words) {
            foreach ($words as $w) {
                if (preg_match('/\b'.preg_quote($w, '/').'\b/u', $q)) {
                    $result['amenities'][] = $slug;
                    break;
                }
            }
        }

        return $result;
    }
}
