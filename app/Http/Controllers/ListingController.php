<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Listing\Actions\CreateOrUpdateListingAction;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Actions\GeneratePropertyCodeAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use App\Domains\Property\Services\PropertyTaxonomyValidationService;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\PropertyStatusHistory;
use App\Jobs\ProcessPropertyImageJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ListingController extends Controller
{
    public function create(Request $request): View
    {
        // 1. If accessed directly at /listings/create without an explicit type parameter, render guided selector
        if ($request->routeIs('listings.create') && ! $request->has('type') && ! $request->has('mode') && ! $request->has('use_existing_property')) {
            return view('listings.create-selector');
        }

        // 2. Resolve listing intent and mode
        $type = strtolower((string) $request->query('type', 'sale'));
        $transactionMode = match ($type) {
            'rent' => 'RENT',
            'lease' => 'LEASE',
            'stay' => 'STAY',
            'project', 'development' => 'NEW_PROJECTS',
            'land', 'sale' => 'BUY',
            default => 'BUY',
        };

        $defaultCategoryCode = $type === 'land' ? 'LND' : '';
        $useExisting = $request->boolean('use_existing_property');
        $existingPropertyId = (string) $request->query('property_id', '');

        $categories = PropertyCategory::query()
            ->where('is_active', true)
            ->with(['propertyTypes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
        $listingTypes = ListingType::query()->where('is_phase1_enabled', true)->orderBy('sort_order')->get();
        $amenities = PropertyAmenity::query()->where('is_active', true)->orderBy('sort_order')->get();
        $features = PropertyFeature::query()->where('is_active', true)->orderBy('sort_order')->get();
        $userProperties = $request->user()
            ? Property::query()->where('owner_id', $request->user()->id)->select('id', 'property_code', 'title_en')->get()
            : collect();

        return view('listings.wizard', [
            'categories' => $categories,
            'listingTypes' => $listingTypes,
            'amenities' => $amenities,
            'features' => $features,
            'userProperties' => $userProperties,
            'initialType' => $type,
            'initialTransactionMode' => $transactionMode,
            'initialCategoryCode' => $defaultCategoryCode,
            'initialUseExisting' => $useExisting,
            'initialExistingPropertyId' => $existingPropertyId,
        ]);
    }

    public function store(
        Request $request,
        CreateOrUpdateListingAction $listingAction,
        GeneratePropertyCodeAction $codeGenerator,
        ImageSanitizerAndHasherService $imageSanitizer,
        UploadDocumentVersionAction $documentUploader,
        PropertyTaxonomyValidationService $taxonomyValidator
    ): RedirectResponse {
        // Normalize boolean representations strictly before validation
        if ($request->has('is_negotiable')) {
            $rawNegotiable = $request->input('is_negotiable');
            if ($rawNegotiable === true || $rawNegotiable === 1 || $rawNegotiable === '1' || $rawNegotiable === 'true' || $rawNegotiable === 'on' || $rawNegotiable === 'yes') {
                $request->merge(['is_negotiable' => true]);
            } elseif ($rawNegotiable === false || $rawNegotiable === 0 || $rawNegotiable === '0' || $rawNegotiable === 'false' || $rawNegotiable === 'off' || $rawNegotiable === 'no') {
                $request->merge(['is_negotiable' => false]);
            }
        }

        // Inspect raw PHP upload errors defensively before standard validation
        $uploadErrors = [];
        if ($request->has('photos') || isset($_FILES['photos'])) {
            $rawPhotos = $request->file('photos');
            if (is_array($rawPhotos)) {
                foreach ($rawPhotos as $i => $file) {
                    if ($file && ! $file->isValid()) {
                        $code = $file->getError();
                        $uploadErrors["photos.{$i}"] = match ($code) {
                            UPLOAD_ERR_INI_SIZE => "Photo #".($i + 1)." exceeds the server upload limit (".ini_get('upload_max_filesize').").",
                            UPLOAD_ERR_FORM_SIZE => "Photo #".($i + 1)." exceeds the maximum allowed form size.",
                            UPLOAD_ERR_PARTIAL => "Photo #".($i + 1)." was only partially uploaded.",
                            UPLOAD_ERR_NO_TMP_DIR => "Server temporary directory is missing.",
                            UPLOAD_ERR_CANT_WRITE => "Failed to write photo #".($i + 1)." to server disk.",
                            UPLOAD_ERR_EXTENSION => "A server extension stopped photo #".($i + 1)." from uploading.",
                            default => "Photo #".($i + 1)." failed to upload (PHP upload error code {$code}).",
                        };
                    }
                }
            }
        }

        if ($request->has('documents') || isset($_FILES['documents'])) {
            $rawDocs = $request->file('documents');
            if (is_array($rawDocs)) {
                foreach ($rawDocs as $i => $file) {
                    if ($file && ! $file->isValid()) {
                        $code = $file->getError();
                        $uploadErrors["documents.{$i}"] = match ($code) {
                            UPLOAD_ERR_INI_SIZE => "Document #".($i + 1)." exceeds the server upload limit (".ini_get('upload_max_filesize').").",
                            UPLOAD_ERR_FORM_SIZE => "Document #".($i + 1)." exceeds the maximum allowed form size.",
                            UPLOAD_ERR_PARTIAL => "Document #".($i + 1)." was only partially uploaded.",
                            UPLOAD_ERR_NO_TMP_DIR => "Server temporary directory is missing.",
                            UPLOAD_ERR_CANT_WRITE => "Failed to write document #".($i + 1)." to server disk.",
                            UPLOAD_ERR_EXTENSION => "A server extension stopped document #".($i + 1)." from uploading.",
                            default => "Document #".($i + 1)." failed to upload (PHP upload error code {$code}).",
                        };
                    }
                }
            }
        }

        if (! empty($uploadErrors)) {
            throw ValidationException::withMessages($uploadErrors);
        }

        $validated = $request->validate([
            'transaction_mode' => 'nullable|string|in:BUY,RENT,LEASE,STAY,NEW_PROJECTS',
            'listing_type_id' => 'nullable|integer',
            'category_code' => 'nullable|string',
            'property_type_code' => 'nullable|string',
            'property_type_id' => 'nullable|integer',
            'title_en' => 'required|string|max:200',
            'title_am' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'region_id' => 'nullable|integer',
            'zone_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'subcity_id' => 'nullable|integer',
            'neighborhood_id' => 'nullable|integer',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'land_area_sqm' => 'nullable|numeric|min:0',
            'building_area_sqm' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'floors' => 'nullable|integer|min:0',
            'parking_spaces' => 'nullable|integer|min:0',
            'amenities' => 'nullable|array',
            'features' => 'nullable|array',
            'photos' => 'nullable|array|max:20',
            'photos.*' => 'file|image|mimes:jpeg,png,webp|max:15360',
            'documents' => 'nullable|array|max:10',
            'documents.*' => 'file|mimes:pdf,jpeg,png,webp|max:20480',
            'price_etb' => 'required|numeric|min:1',
            'price_period' => 'nullable|string',
            'is_negotiable' => 'nullable|boolean',
            'phone_privacy_override' => 'nullable|string',
            'use_existing_property' => 'nullable|boolean',
            'existing_property_id' => 'nullable|string',
            'submit_action' => 'nullable|string|in:draft,moderation',
        ], [
            'photos.*.uploaded' => 'The selected photo (:position) failed to upload. The file may exceed the maximum allowed size or was interrupted.',
            'photos.*.image' => 'The uploaded photo (:position) must be a valid image file (JPEG, PNG, or WebP).',
            'photos.*.mimes' => 'The uploaded photo (:position) must be a file of type: jpeg, png, webp.',
            'photos.*.max' => 'The uploaded photo (:position) may not be greater than 15 MB.',
            'documents.*.uploaded' => 'The selected document (:position) failed to upload. The file may exceed the maximum allowed size or was interrupted.',
            'documents.*.mimes' => 'The uploaded document (:position) must be a file of type: pdf, jpeg, png, webp.',
            'documents.*.max' => 'The uploaded document (:position) may not be greater than 20 MB.',
        ]);

        $user = $request->user();

        // Enforce explicit category and property type selections for new properties (A.1)
        if (empty($validated['use_existing_property'])) {
            if (empty($validated['category_code']) && empty($request->input('category_id'))) {
                throw ValidationException::withMessages([
                    'category_id' => ['The property category field is required.'],
                    'category_code' => ['The property category field is required.'],
                ]);
            }
            if (empty($validated['property_type_code']) && empty($validated['property_type_id'])) {
                throw ValidationException::withMessages([
                    'property_type_id' => ['The property type field is required.'],
                    'property_type_code' => ['The property type field is required.'],
                    'property_type' => ['The property type field is required.'],
                ]);
            }
        }

        // Validate administrative location hierarchy relationships (SRS Section 11)
        if (! empty($validated['city_id']) && ! empty($validated['region_id'])) {
            $city = Location::query()->find($validated['city_id']);
            if ($city && $city->parent_id && (int) $city->parent_id !== (int) $validated['region_id']) {
                $ancestorIds = array_filter(explode('/', trim((string) $city->ancestry_path, '/')));
                if (! empty($ancestorIds) && ! in_array((string) $validated['region_id'], $ancestorIds, true)) {
                    throw ValidationException::withMessages([
                        'city_id' => ['The selected city does not belong to the selected region.'],
                    ]);
                }
            }
        }

        if (! empty($validated['subcity_id']) && ! empty($validated['city_id'])) {
            $subcity = Location::query()->find($validated['subcity_id']);
            if ($subcity && (int) $subcity->parent_id !== (int) $validated['city_id']) {
                throw ValidationException::withMessages([
                    'subcity_id' => ['The selected sub-city does not belong to the selected city.'],
                ]);
            }
        }

        $trackedStoredFiles = [];

        try {
            $result = DB::transaction(function () use (
                $request,
                $validated,
                $user,
                $listingAction,
                $codeGenerator,
                $imageSanitizer,
                $documentUploader,
                $taxonomyValidator,
                &$trackedStoredFiles
            ) {
                // 1. Resolve or Create Physical Property (Strict Asset Separation per SRS Section 3.1)
                if (! empty($validated['use_existing_property']) && ! empty($validated['existing_property_id'])) {
                    $userCompanyIds = $user->companies()->pluck('id');
                    $property = Property::query()
                        ->where(function ($pq) use ($user, $userCompanyIds): void {
                            $pq->where('owner_id', $user->id);
                            if ($userCompanyIds->isNotEmpty()) {
                                $pq->orWhereIn('company_id', $userCompanyIds);
                            }
                        })
                        ->where(function ($q) use ($validated) {
                            $q->where('id', $validated['existing_property_id'])
                                ->orWhere('property_code', $validated['existing_property_id']);
                        })
                        ->firstOrFail();
                } else {
                    $categoryIdentifier = $validated['category_code'] ?? $request->input('category_id');
                    $typeIdentifier = $validated['property_type_code'] ?? $validated['property_type_id'];

                    $taxonomy = $taxonomyValidator->validateCategoryAndType($categoryIdentifier, $typeIdentifier);
                    $category = $taxonomy['category'];
                    $propertyType = $taxonomy['propertyType'];

                    // A.2: Dynamic, location-derived prefix resolution
                    $cityCode = 'AA';
                    $areaCode = 'GEN';

                    if (! empty($validated['city_id'])) {
                        $cityLoc = Location::query()->find($validated['city_id']);
                        if ($cityLoc && ! empty($cityLoc->code)) {
                            $cityCode = $cityLoc->code;
                        }
                    } elseif (! empty($validated['region_id'])) {
                        $regLoc = Location::query()->find($validated['region_id']);
                        if ($regLoc && ! empty($regLoc->code)) {
                            $cityCode = str_replace('-REG', '', $regLoc->code);
                        }
                    }

                    if (! empty($validated['subcity_id'])) {
                        $scLoc = Location::query()->find($validated['subcity_id']);
                        if ($scLoc && ! empty($scLoc->code)) {
                            $areaCode = $scLoc->code;
                        }
                    } elseif (! empty($validated['neighborhood_id'])) {
                        $nbLoc = Location::query()->find($validated['neighborhood_id']);
                        if ($nbLoc && ! empty($nbLoc->code)) {
                            $areaCode = $nbLoc->code;
                        }
                    }

                    $propertyCode = $codeGenerator->execute(
                        countryCode: 'ETH',
                        cityCode: $cityCode,
                        areaCode: $areaCode,
                        categoryCode: $category->code
                    );

                    // Automatic zone_id derivation from location hierarchy ancestry
                    $zoneId = $validated['zone_id'] ?? null;
                    if (! $zoneId) {
                        $anchorId = $validated['neighborhood_id'] ?? $validated['subcity_id'] ?? $validated['city_id'] ?? null;
                        if ($anchorId) {
                            $anchor = Location::query()->find($anchorId);
                            if ($anchor && $anchor->ancestry_path) {
                                $ancestorIds = array_filter(explode('/', trim((string) $anchor->ancestry_path, '/')));
                                if (! empty($ancestorIds)) {
                                    $zoneAncestor = Location::query()
                                        ->whereIn('id', $ancestorIds)
                                        ->where('level', 'zone')
                                        ->first();
                                    $zoneId = $zoneAncestor?->id;
                                }
                            }
                        }
                    }

                    $property = Property::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'property_code' => $propertyCode,
                        'owner_id' => $user->id,
                        'category_id' => $category->id,
                        'property_type_id' => $propertyType->id,
                        'region_id' => $validated['region_id'] ?? null,
                        'zone_id' => $zoneId,
                        'city_id' => $validated['city_id'] ?? null,
                        'subcity_id' => $validated['subcity_id'] ?? null,
                        'neighborhood_id' => $validated['neighborhood_id'] ?? null,
                        'title_en' => $validated['title_en'],
                        'title_am' => $validated['title_am'] ?? null,
                        'description_en' => $validated['description_en'] ?? null,
                        'land_area_sqm' => $validated['land_area_sqm'] ?? null,
                        'building_area_sqm' => $validated['building_area_sqm'] ?? null,
                        'bedrooms' => $validated['bedrooms'] ?? null,
                        'bathrooms' => $validated['bathrooms'] ?? null,
                        'floors' => $validated['floors'] ?? null,
                        'parking_spaces' => $validated['parking_spaces'] ?? 0,
                        'latitude' => $validated['latitude'] ?? null,
                        'longitude' => $validated['longitude'] ?? null,
                        'status' => PropertyStatus::Available,
                    ]);

                    // Dynamic category features
                    if (! empty($validated['features'])) {
                        $validatedFeatures = $taxonomyValidator->validateFeatures($category, $validated['features']);
                        foreach ($validatedFeatures as $featSlug => $valText) {
                            $featId = DB::table('property_features')->where('slug', $featSlug)->value('id');
                            if ($featId) {
                                DB::table('property_feature_values')->updateOrInsert(
                                    ['property_id' => $property->id, 'feature_id' => $featId],
                                    ['value_text' => $valText, 'updated_at' => now(), 'created_at' => now()]
                                );
                            }
                        }
                    }

                    // Sync Amenities with active status validation
                    if (! empty($validated['amenities'])) {
                        $submittedAmenities = (array) $validated['amenities'];
                        $activeAmenities = DB::table('property_amenities')
                            ->whereIn('slug', $submittedAmenities)
                            ->where('is_active', true)
                            ->pluck('id', 'slug')
                            ->all();

                        $invalidAmenities = array_diff($submittedAmenities, array_keys($activeAmenities));
                        if (! empty($invalidAmenities)) {
                            throw ValidationException::withMessages([
                                'amenities' => ['The selected amenities contain inactive or invalid options: '.implode(', ', $invalidAmenities)],
                            ]);
                        }

                        $property->amenities()->sync(array_values($activeAmenities));
                    }
                }

                // 2. Handle Image Uploads with Server-side EXIF/GPS stripping & Perceptual Hashing
                if ($request->hasFile('photos')) {
                    $photos = $request->file('photos');
                    if (is_array($photos)) {
                        app(\App\Domains\Subscription\Services\SubscriptionEntitlementService::class)->validatePropertyImages(
                            $user,
                            $property,
                            count($photos)
                        );

                        $tagCycle = ['exterior', 'living_room', 'bedroom', 'kitchen', 'bathroom', 'compound'];
                        foreach ($photos as $idx => $photo) {
                            if (! $photo->isValid()) {
                                continue;
                            }

                            $filename = sprintf('%s_%d.webp', Str::uuid(), $idx + 1);
                            $storageDir = storage_path('app/public/properties/'.$property->id);
                            if (! is_dir($storageDir)) {
                                mkdir($storageDir, 0755, true);
                            }
                            $destinationPath = $storageDir.'/'.$filename;

                            try {
                                $imageSanitizer->stripExifAndSaveWebp($photo->getRealPath(), $destinationPath);
                                $dHash = $imageSanitizer->computePerceptualHash($destinationPath);
                            } catch (\Throwable $err) {
                                if (file_exists($destinationPath)) {
                                    @unlink($destinationPath);
                                }
                                throw $err;
                            }

                            $relativeSanitizedPath = "properties/{$property->id}/{$filename}";
                            $trackedStoredFiles[] = ['disk' => 'public', 'path' => $relativeSanitizedPath];
                            $roomTag = $tagCycle[$idx % count($tagCycle)];

                            $imageRecord = PropertyImage::query()->create([
                                'property_id' => $property->id,
                                'disk' => 'public',
                                'original_sanitized_path' => $relativeSanitizedPath,
                                'webp_medium_path' => $relativeSanitizedPath,
                                'room_tag' => $roomTag,
                                'perceptual_hash' => $dHash,
                                'exif_stripped' => true,
                                'is_cover' => $idx === 0,
                                'sort_order' => $idx + 1,
                            ]);

                            // Dispatch async variant processing strictly after transaction commits
                            DB::afterCommit(function () use ($imageRecord): void {
                                ProcessPropertyImageJob::dispatch($imageRecord);
                            });
                        }
                    }
                }

                // 3. Handle Document Uploads into Private Vault
                if ($request->hasFile('documents')) {
                    $docs = $request->file('documents');
                    if (is_array($docs)) {
                        foreach ($docs as $docFile) {
                            if ($docFile->isValid()) {
                                $documentUploader->execute(
                                    uploader: $user,
                                    documentableType: $property->getMorphClass(),
                                    documentableId: $property->id,
                                    documentTypeCode: 'ownership_deed',
                                    title: $docFile->getClientOriginalName(),
                                    file: $docFile,
                                    accessLevel: DocumentAccessLevel::VerificationOnly
                                );
                            }
                        }
                    }
                }

                // 4. Resolve and Validate Listing Type (SRS Section 8)
                $listingTypeId = $validated['listing_type_id'] ?? null;
                if ($listingTypeId) {
                    $selectedListingType = ListingType::query()
                        ->where('id', $listingTypeId)
                        ->where('is_phase1_enabled', true)
                        ->first();

                    if (! $selectedListingType) {
                        throw ValidationException::withMessages([
                            'listing_type_id' => ['The selected listing type is invalid or inactive.'],
                        ]);
                    }
                } else {
                    $mode = $validated['transaction_mode'] ?? 'BUY';
                    $listingType = ListingType::query()->where('transaction_mode', $mode)->where('is_phase1_enabled', true)->first()
                        ?? ListingType::query()->where('is_phase1_enabled', true)->first();
                    $listingTypeId = $listingType?->id;
                    if (! $listingTypeId) {
                        throw ValidationException::withMessages([
                            'listing_type_id' => ['No active listing type is available.'],
                        ]);
                    }
                }

                // 5. Determine target status (PendingReview if submitted for moderation)
                $targetStatus = ($request->input('submit_action') === 'draft')
                    ? ListingStatus::Draft->value
                    : ListingStatus::PendingReview->value;

                // 6. Create or Update Listing via Action
                $listing = $listingAction->execute(
                    actor: $user,
                    property: $property,
                    payload: [
                        'listing_type_id' => $listingTypeId,
                        'price_etb' => $validated['price_etb'],
                        'price_period' => $validated['price_period'] ?? 'total',
                        'is_negotiable' => $validated['is_negotiable'] ?? true,
                        'phone_privacy_override' => $validated['phone_privacy_override'] ?? null,
                        'status' => $targetStatus,
                    ]
                );

                // Auto-create StayListingProfile if transaction mode is STAY
                if (($validated['transaction_mode'] ?? '') === 'STAY') {
                    $host = \App\Domains\Host\Models\Host::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'display_name' => $user->name ?? 'Host',
                            'company_id' => $property->company_id,
                            'is_superhost' => false,
                            'verification_status' => 'verified',
                            'phone_verified' => true,
                        ]
                    );

                    \App\Domains\Stay\Models\StayListingProfile::updateOrCreate(
                        ['listing_id' => $listing->id],
                        [
                            'property_id' => $property->id,
                            'host_id' => $host->id,
                            'accommodation_model' => 'entire_place',
                            'base_nightly_rate_etb' => $validated['price_etb'],
                            'weekend_nightly_rate_etb' => round((float) $validated['price_etb'] * 1.15, 2),
                            'cleaning_fee_etb' => 500.00,
                            'security_deposit_etb' => 1500.00,
                            'guest_service_fee_pct' => 5.0,
                            'tourism_tax_pct' => 15.00,
                            'min_stay_nights' => 1,
                            'max_stay_nights' => 30,
                            'check_in_after_time' => '14:00',
                            'check_out_before_time' => '11:00',
                            'max_guests' => max(1, ($property->bedrooms ?? 1) * 2),
                            'max_adults' => max(1, ($property->bedrooms ?? 1) * 2),
                            'max_children' => 2,
                            'max_infants' => 1,
                            'allows_pets' => false,
                            'instant_book_enabled' => true,
                            'cancellation_policy' => 'moderate',
                            'hold_duration_minutes' => 15,
                        ]
                    );
                }

                return ['listing' => $listing, 'property' => $property, 'targetStatus' => $targetStatus];
            });
        } catch (\Throwable $e) {
            // Compensating cleanup of written files
            foreach ($trackedStoredFiles as $file) {
                try {
                    Storage::disk($file['disk'])->delete($file['path']);
                } catch (\Throwable $cleanupErr) {
                    \Illuminate\Support\Facades\Log::error("Failed compensating cleanup of storage file {$file['path']}: {$cleanupErr->getMessage()}");
                }
            }
            throw $e;
        }

        $listing = $result['listing'];
        $property = $result['property'];
        $targetStatus = $result['targetStatus'];

        $statusMsg = $targetStatus === ListingStatus::PendingReview->value
            ? "Listing {$listing->listing_code} submitted for moderation review! Reference Property: {$property->property_code}."
            : "Listing {$listing->listing_code} saved as draft.";

        return redirect()->route('dashboard')
            ->with('status', $statusMsg)
            ->with('clear_wizard_draft', true);
    }
}
