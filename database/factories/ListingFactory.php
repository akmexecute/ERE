<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        $userId = User::query()->first()->id ?? User::factory()->create()->id;

        // Ensure category and property type
        $categoryId = DB::table('property_categories')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('property_categories')->insertGetId([
                'code' => 'RES',
                'slug' => 'residential',
                'name_en' => 'Residential',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $propertyTypeId = DB::table('property_types')->value('id');
        if (! $propertyTypeId) {
            $propertyTypeId = DB::table('property_types')->insertGetId([
                'category_id' => $categoryId,
                'code' => 'VILLA',
                'slug' => 'villa',
                'name_en' => 'Villa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $propertyId = DB::table('properties')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'owner_id' => $userId,
            'category_id' => $categoryId,
            'property_type_id' => $propertyTypeId,
            'title_en' => 'Bole Luxury Villa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listingTypeId = DB::table('listing_types')->value('id');
        if (! $listingTypeId) {
            $listingTypeId = DB::table('listing_types')->insertGetId([
                'code' => 'FOR_SALE',
                'slug' => 'for-sale',
                'transaction_mode' => 'BUY',
                'name_en' => 'For Sale',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'slug' => 'luxury-villa-' . Str::random(8),
            'property_id' => $propertyId,
            'published_by_user_id' => $userId,
            'listing_type_id' => $listingTypeId,
            'transaction_mode' => 'BUY',
            'price_etb' => 25000000.00,
            'currency' => 'ETB',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
