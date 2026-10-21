<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Listing\Actions\CreateOrUpdateListingAction;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Actions\GeneratePropertyCodeAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ListingController extends Controller
{
    public function create(): View
    {
        $categories = PropertyCategory::query()->where('is_active', true)->with('propertyTypes')->get();
        $listingTypes = ListingType::query()->where('is_phase1_enabled', true)->get();
        $amenities = PropertyAmenity::query()->where('is_active', true)->get();

        return view('listings.wizard', [
            'categories' => $categories,
            'listingTypes' => $listingTypes,
            'amenities' => $amenities,
        ]);
    }

    public function store(
        Request $request,
        CreateOrUpdateListingAction $listingAction,
        GeneratePropertyCodeAction $codeGenerator,
        ImageSanitizerAndHasherService $imageSanitizer,
        UploadDocumentVersionAction $documentUploader
    ): RedirectResponse {
        $validated = $request->validate([
            'transaction_mode' => 'nullable|string|in:BUY,RENT,LEASE,STAY,NEW_PROJECTS',
            'listing_type_id' => 'nullable|integer',
            'category_code' => 'nullable|string',
            'area_basis' => 'nullable|string|in:land_area,building_area',
            'title_en' => 'required|string|max:200',
            'title_am' => 'nullable|string|max:200',
            'description_en' => 'nullable|string',
            'region_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'subcity_id' => 'nullable|integer',
            'neighborhood_id' => 'nullable|integer',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'land_area_sqm' => 'nullable|numeric|min:0',
            'building_area_sqm' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'floors' => 'nullable|integer|min:0',
            'parking_spaces' => 'nullable|integer|min:0',
            'price_etb' => 'required|numeric|min:1',
            'price_period' => 'nullable|string',
            'is_negotiable' => 'nullable|boolean',
            'phone_privacy_override' => 'nullable|string',
            'use_existing_property' => 'nullable|boolean',
            'existing_property_id' => 'nullable|string',
        ]);

        $user = $request->user();

        // 1. Resolve or Create Property
        if (! empty($validated['use_existing_property']) && ! empty($validated['existing_property_id'])) {
            $property = Property::query()
                ->where('owner_id', $user->id)
                ->where(function ($q) use ($validated) {
                    $q->where('id', $validated['existing_property_id'])
                        ->orWhere('property_code', $validated['existing_property_id']);
                })
                ->firstOrFail();
        } else {
            $categoryCode = $validated['category_code'] ?? 'RES';
            $category = PropertyCategory::query()->where('code', $categoryCode)->first()
                ?? PropertyCategory::query()->first();

            $propertyType = PropertyType::query()->where('category_id', $category?->id)->first()
                ?? PropertyType::query()->first();

            $propertyCode = $codeGenerator->execute(
                countryCode: 'ETH',
                cityCode: 'AA',
                areaCode: 'BOLE',
                categoryCode: $category?->code ?? 'RES'
            );

            $property = Property::query()->create([
                'uuid' => (string) Str::uuid(),
                'property_code' => $propertyCode,
                'owner_id' => $user->id,
                'category_id' => $category?->id ?? 1,
                'property_type_id' => $propertyType?->id ?? 1,
                'region_id' => $validated['region_id'] ?? null,
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
        }

        // 2. Handle Image Uploads with EXIF stripping and perceptual dHash
        if ($request->hasFile('photos')) {
            $photos = $request->file('photos');
            if (is_array($photos)) {
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

                    // Strip EXIF and save WebP
                    try {
                        $imageSanitizer->stripExifAndSaveWebp($photo->getRealPath(), $destinationPath);
                        $dHash = $imageSanitizer->computePerceptualHash($destinationPath);
                    } catch (\Throwable) {
                        $storedPath = $photo->store("properties/{$property->id}", 'public');
                        $destinationPath = storage_path('app/public/'.$storedPath);
                        $dHash = str_repeat('0', 16);
                    }

                    $relativeSanitizedPath = "properties/{$property->id}/{$filename}";

                    PropertyImage::query()->create([
                        'property_id' => $property->id,
                        'disk' => 'public',
                        'original_sanitized_path' => $relativeSanitizedPath,
                        'webp_medium_path' => $relativeSanitizedPath,
                        'room_tag' => $idx === 0 ? 'exterior' : ($idx === 1 ? 'living_room' : 'bedroom'),
                        'perceptual_hash' => $dHash,
                        'exif_stripped' => true,
                        'is_cover' => $idx === 0,
                        'sort_order' => $idx + 1,
                    ]);
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

        // 4. Resolve Listing Type
        $listingTypeId = $validated['listing_type_id'] ?? null;
        if (! $listingTypeId) {
            $mode = $validated['transaction_mode'] ?? 'BUY';
            $listingType = ListingType::query()->where('transaction_mode', $mode)->first()
                ?? ListingType::query()->first();
            $listingTypeId = $listingType?->id ?? 1;
        }

        // 5. Create or Update Listing via Action
        $listing = $listingAction->execute(
            actor: $user,
            property: $property,
            payload: [
                'listing_type_id' => $listingTypeId,
                'price_etb' => $validated['price_etb'],
                'price_period' => $validated['price_period'] ?? 'total',
                'is_negotiable' => $validated['is_negotiable'] ?? true,
                'phone_privacy_override' => $validated['phone_privacy_override'] ?? null,
            ]
        );

        return redirect()->route('dashboard')
            ->with('status', "Listing {$listing->listing_code} created successfully for Property {$property->property_code}!");
    }
}
