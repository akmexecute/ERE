<?php

declare(strict_types=1);

namespace App\Domains\Listing\Actions;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Support\Money\EtbFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrUpdateListingAction
{
    public function __construct(
        private CalculateListingQualityScoreAction $qualityScorer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $actor, Property $property, array $payload, ?Listing $existingListing = null): Listing
    {
        $listingType = DB::table('listing_types')->where('id', $payload['listing_type_id'])->firstOrFail();

        // Business Rule (SRS Section 3.1): Prevent duplicate active listings of the exact same transaction_mode on one Property
        $conflictingQuery = Listing::query()
            ->where('property_id', $property->id)
            ->where('transaction_mode', $listingType->transaction_mode)
            ->whereIn('status', [
                ListingStatus::PendingReview->value,
                ListingStatus::Approved->value,
                ListingStatus::Published->value,
            ]);

        if ($existingListing) {
            $conflictingQuery->where('id', '!=', $existingListing->id);
        }

        if ($conflictingQuery->exists()) {
            throw ValidationException::withMessages([
                'listing_type_id' => ["This property already has an active {$listingType->transaction_mode} listing."],
            ]);
        }

        // Determine appropriate area denominator per PropertyType (SRS Section 36)
        $areaBasis = DB::table('property_types')
            ->where('id', $property->property_type_id)
            ->value('area_basis') ?? 'building_area';

        $relevantAreaSqm = $areaBasis === 'land_area'
            ? ($property->land_area_sqm ?: $property->building_area_sqm)
            : ($property->building_area_sqm ?: $property->land_area_sqm);

        $priceEtb = (float) $payload['price_etb'];
        $pricePerSqm = EtbFormatter::calculatePricePerSqm($priceEtb, $relevantAreaSqm);

        $photoCount = DB::table('property_images')->where('property_id', $property->id)->count();
        $roomTags = DB::table('property_images')->where('property_id', $property->id)->pluck('room_tag')->all();
        $amenityCount = DB::table('property_amenity_pivot')->where('property_id', $property->id)->count();
        $docCount = DB::table('documents')
            ->where('documentable_type', $property->getMorphClass())
            ->where('documentable_id', $property->id)
            ->count();

        $quality = $this->qualityScorer->execute($property, $priceEtb, $photoCount, $roomTags, $amenityCount, $docCount);

        return DB::transaction(function () use (
            $actor,
            $property,
            $payload,
            $existingListing,
            $listingType,
            $priceEtb,
            $pricePerSqm,
            $quality
        ): Listing {
            if (! $existingListing) {
                $seq = Listing::withTrashed()->count() + 1;
                $listingCode = sprintf('LST-%s-%07d', date('Y'), $seq);
                $slug = Str::slug($property->title_en.'-'.$property->property_code.'-'.$listingType->slug);

                return Listing::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'listing_code' => $listingCode,
                    'slug' => $slug,
                    'property_id' => $property->id,
                    'published_by_user_id' => $actor->id,
                    'company_id' => $property->company_id,
                    'listing_type_id' => $listingType->id,
                    'transaction_mode' => $listingType->transaction_mode,
                    'price_etb' => $priceEtb,
                    'currency' => 'ETB',
                    'price_period' => $payload['price_period'] ?? $listingType->default_price_period,
                    'price_per_sqm_etb' => $pricePerSqm,
                    'is_negotiable' => (bool) ($payload['is_negotiable'] ?? true),
                    'quality_score' => $quality['score'],
                    'missing_quality_fields' => $quality['missing_fields'],
                    'phone_privacy_override' => $payload['phone_privacy_override'] ?? null,
                    'status' => ListingStatus::Draft->value,
                ]);
            }

            $oldPrice = (float) $existingListing->price_etb;
            $existingListing->update([
                'listing_type_id' => $listingType->id,
                'transaction_mode' => $listingType->transaction_mode,
                'price_etb' => $priceEtb,
                'price_period' => $payload['price_period'] ?? $existingListing->price_period,
                'price_per_sqm_etb' => $pricePerSqm,
                'is_negotiable' => (bool) ($payload['is_negotiable'] ?? $existingListing->is_negotiable),
                'quality_score' => $quality['score'],
                'missing_quality_fields' => $quality['missing_fields'],
                'phone_privacy_override' => $payload['phone_privacy_override'] ?? $existingListing->phone_privacy_override,
            ]);

            // Record immutable Price History when price changes (SRS Sections 189 & 212)
            if (abs($oldPrice - $priceEtb) >= 1.0 && $oldPrice > 0) {
                $delta = $priceEtb - $oldPrice;
                $pct = round(($delta / $oldPrice) * 100, 2);

                DB::table('listing_price_histories')->insert([
                    'listing_id' => $existingListing->id,
                    'old_price_etb' => $oldPrice,
                    'new_price_etb' => $priceEtb,
                    'change_amount_etb' => $delta,
                    'change_percentage' => $pct,
                    'changed_by' => $actor->id,
                    'reason' => $payload['price_change_reason'] ?? 'Seller updated listing price',
                    'created_at' => now(),
                ]);
            }

            return $existingListing->fresh();
        });
    }
}
