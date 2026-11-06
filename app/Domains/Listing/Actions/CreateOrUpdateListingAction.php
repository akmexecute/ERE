<?php

declare(strict_types=1);

namespace App\Domains\Listing\Actions;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyType;
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

        // 1. Authoritative Area Denominator (SRS Section 36 & Master Prompt Section 9.1)
        // Strict adherence to property type area basis — never silently substitute land_area for building_area or vice versa.
        $propertyType = PropertyType::query()->find($property->property_type_id);
        $areaBasis = $propertyType?->area_basis ?? 'building_area';

        $relevantAreaSqm = match ($areaBasis) {
            'land_area' => ($property->land_area_sqm !== null && (float) $property->land_area_sqm > 0)
                ? (float) $property->land_area_sqm
                : null,
            default => ($property->building_area_sqm !== null && (float) $property->building_area_sqm > 0)
                ? (float) $property->building_area_sqm
                : null,
        };

        $priceEtb = (float) $payload['price_etb'];
        $pricePerSqm = ($relevantAreaSqm !== null && $relevantAreaSqm > 0 && $priceEtb > 0)
            ? EtbFormatter::calculatePricePerSqm($priceEtb, $relevantAreaSqm)
            : null;

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
            // Business Rule & Race-Condition Prevention (SRS Section 3.1 & Master Prompt Section 8.4)
            // Check for active listing with row locking
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

            if ($conflictingQuery->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'listing_type_id' => ["This property already has an active {$listingType->transaction_mode} listing."],
                ]);
            }

            if (! $existingListing) {
                $listingCode = $this->generateAtomicListingCode();
                $slug = $this->generateUniqueSlug($property, $listingType);
                $status = $payload['status'] ?? ListingStatus::Draft->value;

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
                    'status' => $status,
                    'submitted_at' => $status === ListingStatus::PendingReview->value ? now() : null,
                ]);
            }

            $oldPrice = (float) $existingListing->price_etb;
            $newStatus = $payload['status'] ?? $existingListing->status;

            $updateData = [
                'listing_type_id' => $listingType->id,
                'transaction_mode' => $listingType->transaction_mode,
                'price_etb' => $priceEtb,
                'price_period' => $payload['price_period'] ?? $existingListing->price_period,
                'price_per_sqm_etb' => $pricePerSqm,
                'is_negotiable' => (bool) ($payload['is_negotiable'] ?? $existingListing->is_negotiable),
                'quality_score' => $quality['score'],
                'missing_quality_fields' => $quality['missing_fields'],
                'phone_privacy_override' => $payload['phone_privacy_override'] ?? $existingListing->phone_privacy_override,
            ];

            if ($newStatus !== $existingListing->status) {
                $updateData['status'] = $newStatus;
                if ($newStatus === ListingStatus::PendingReview->value) {
                    $updateData['submitted_at'] = now();
                }
            }

            $existingListing->update($updateData);

            // Record immutable Price History when price changes (SRS Sections 189 & 212)
            $oldCents = EtbFormatter::toMinorUnits($oldPrice);
            $newCents = EtbFormatter::toMinorUnits($priceEtb);

            if ($oldCents !== $newCents && $oldCents > 0) {
                $deltaCents = $newCents - $oldCents;
                $delta = EtbFormatter::fromMinorUnits($deltaCents);
                $pct = round(((float) $deltaCents / (float) $oldCents) * 100, 2);

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

    /**
     * Generates an atomic sequential listing code (e.g. LST-2026-0000001).
     * Avoids count() + 1 concurrency collision.
     */
    private function generateAtomicListingCode(): string
    {
        $year = date('Y');
        $prefix = "LST-{$year}";
        $driver = DB::getDriverName();

        do {
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                $timestampSql = $driver === 'pgsql' ? 'NOW()' : 'CURRENT_TIMESTAMP';
                $sql = "INSERT INTO property_code_sequences (prefix_key, last_value, updated_at)
                        VALUES (?, 1, {$timestampSql})
                        ON CONFLICT (prefix_key)
                        DO UPDATE SET last_value = property_code_sequences.last_value + 1, updated_at = {$timestampSql}
                        RETURNING last_value";

                $row = DB::selectOne($sql, [$prefix]);
                $seq = (int) ($row->last_value ?? 1);
            } else {
                $seq = (int) DB::table('property_code_sequences')
                    ->where('prefix_key', $prefix)
                    ->increment('last_value');

                if ($seq === 0) {
                    DB::table('property_code_sequences')->insert([
                        'prefix_key' => $prefix,
                        'last_value' => 1,
                        'updated_at' => now(),
                    ]);
                    $seq = 1;
                }
            }

            $candidate = sprintf('LST-%s-%07d', $year, $seq);
        } while (Listing::query()->where('listing_code', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generates a deterministic, unique slug with collision avoidance.
     */
    private function generateUniqueSlug(Property $property, object $listingType): string
    {
        $baseSlug = Str::slug($property->title_en . '-' . $property->property_code . '-' . $listingType->slug);
        if (empty($baseSlug)) {
            $baseSlug = 'listing-' . Str::lower((string) Str::random(8));
        }

        $slug = $baseSlug;
        $counter = 1;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = sprintf('%s-%d', $baseSlug, $counter);
        }

        return $slug;
    }
}
