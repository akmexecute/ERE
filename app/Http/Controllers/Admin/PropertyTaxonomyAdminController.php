<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PropertyTaxonomyAdminController extends Controller
{
    private function checkTaxonomyAuthorization(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAuthorized = false;
        if (method_exists($user, 'hasPermission') && $user->hasPermission('property.update')) {
            $isAuthorized = true;
        } elseif (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'platform_admin', 'property_admin'])) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            abort(403, 'Unauthorized access to property taxonomy administration.');
        }
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\View\View
    {
        $this->checkTaxonomyAuthorization($request);

        $categories = PropertyCategory::query()->with(['propertyTypes' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get();
        $propertyTypes = PropertyType::query()->with('category')->orderBy('sort_order')->get();
        $listingTypes = ListingType::query()->orderBy('sort_order')->get();
        $amenities = PropertyAmenity::query()->orderBy('group_key')->orderBy('sort_order')->get();
        $features = PropertyFeature::query()->orderBy('sort_order')->get();

        $categoryRes = $categories->firstWhere('code', 'RES');
        $categoryLnd = $categories->firstWhere('code', 'LND');
        $categoryCom = $categories->firstWhere('code', 'COM');
        $categoryInd = $categories->firstWhere('code', 'IND');
        $categoryHos = $categories->firstWhere('code', 'HSP');

        if ($request->wantsJson()) {
            return response()->json([
                'categories' => $categories,
                'property_types' => $propertyTypes,
                'listing_types' => $listingTypes,
                'amenities' => $amenities,
                'features' => $features,
                'category_res' => $categoryRes,
                'category_lnd' => $categoryLnd,
                'category_com' => $categoryCom,
                'category_ind' => $categoryInd,
                'category_hos' => $categoryHos,
            ]);
        }

        return view('admin.taxonomy.index', compact(
            'categories',
            'propertyTypes',
            'listingTypes',
            'amenities',
            'features',
            'categoryRes',
            'categoryLnd',
            'categoryCom',
            'categoryInd',
            'categoryHos'
        ));
    }

    public function storeCategory(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'code' => 'required|string|max:16|unique:property_categories,code',
            'slug' => 'nullable|string|max:100',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name_en']);

        if (PropertyCategory::query()->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . strtolower(Str::random(4));
        }

        $category = PropertyCategory::query()->create([
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'code' => $code,
            'slug' => $slug,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Property category created successfully.',
                'category' => $category,
            ], 201);
        }

        return back()->with('success', "Property category '{$category->name_en}' created successfully.");
    }

    public function updateCategory(PropertyCategory $category, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : null,
            'sort_order' => (int) ($validated['sort_order'] ?? $category->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category updated successfully.',
                'category' => $category->fresh(),
            ]);
        }

        return back()->with('success', "Category '{$category->name_en}' updated successfully.");
    }

    public function toggleCategory(PropertyCategory $category, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category status updated.',
                'category' => $category->fresh(),
            ]);
        }

        return back()->with('success', 'Category status updated.');
    }

    public function destroyCategory(PropertyCategory $category, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $hasProperties = DB::table('properties')->where('category_id', $category->id)->exists();
        $hasTypes = $category->propertyTypes()->exists();

        if ($hasProperties || $hasTypes) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete category referenced by active properties or types. Please deactivate it instead.'],
            ]);
        }

        $category->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Category deleted successfully.']);
        }

        return back()->with('success', 'Category deleted successfully.');
    }

    public function storePropertyType(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:property_categories,id',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'code' => 'required|string|max:24',
            'slug' => 'nullable|string|max:100',
            'area_basis' => 'required|in:building_area,land_area',
            'requires_bedrooms' => 'nullable|boolean',
            'requires_floors' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name_en']);

        if (PropertyType::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => ['The property type code has already been taken.']]);
        }
        if (PropertyType::query()->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . strtolower(Str::random(4));
        }

        $propertyType = PropertyType::query()->create([
            'category_id' => (int) $validated['category_id'],
            'code' => $code,
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'area_basis' => $validated['area_basis'],
            'requires_bedrooms' => $request->has('requires_bedrooms') ? $request->boolean('requires_bedrooms') : true,
            'requires_floors' => $request->has('requires_floors') ? $request->boolean('requires_floors') : true,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Property type created successfully.',
                'property_type' => $propertyType,
            ], 201);
        }

        return back()->with('success', "Property type '{$propertyType->name_en}' created successfully.");
    }

    public function updatePropertyType(PropertyType $propertyType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'category_id' => 'required|exists:property_categories,id',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'code' => 'required|string|max:24',
            'slug' => 'nullable|string|max:100',
            'area_basis' => 'required|in:building_area,land_area',
            'requires_bedrooms' => 'nullable|boolean',
            'requires_floors' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : $propertyType->slug;

        if (PropertyType::query()->where('code', $code)->where('id', '!=', $propertyType->id)->exists()) {
            throw ValidationException::withMessages(['code' => ['The property type code has already been taken.']]);
        }
        if (PropertyType::query()->where('slug', $slug)->where('id', '!=', $propertyType->id)->exists()) {
            $slug = $slug . '-' . strtolower(Str::random(4));
        }

        $propertyType->update([
            'category_id' => (int) $validated['category_id'],
            'code' => $code,
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'area_basis' => $validated['area_basis'],
            'requires_bedrooms' => $request->has('requires_bedrooms') ? $request->boolean('requires_bedrooms') : $propertyType->requires_bedrooms,
            'requires_floors' => $request->has('requires_floors') ? $request->boolean('requires_floors') : $propertyType->requires_floors,
            'sort_order' => (int) ($validated['sort_order'] ?? $propertyType->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $propertyType->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Property type updated successfully.',
                'property_type' => $propertyType->fresh(),
            ]);
        }

        return back()->with('success', "Property type '{$propertyType->name_en}' updated successfully.");
    }

    public function togglePropertyType(PropertyType $propertyType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $propertyType->update([
            'is_active' => ! $propertyType->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Property type status updated.',
                'property_type' => $propertyType->fresh(),
            ]);
        }

        return back()->with('success', 'Property type status updated.');
    }

    public function destroyPropertyType(PropertyType $propertyType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $hasProperties = DB::table('properties')->where('property_type_id', $propertyType->id)->exists();
        if ($hasProperties) {
            throw ValidationException::withMessages([
                'property_type' => ['Cannot delete property type referenced by active properties. Please deactivate it instead.'],
            ]);
        }

        $propertyType->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Property type deleted successfully.']);
        }

        return back()->with('success', 'Property type deleted successfully.');
    }

    public function storeListingType(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'slug' => 'nullable|string|max:80',
            'transaction_mode' => 'required|string|in:BUY,RENT,LEASE,STAY,NEW_PROJECTS',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'default_price_period' => 'required|in:total,per_month,per_year,per_night',
            'sort_order' => 'nullable|integer|min:0',
            'is_phase1_enabled' => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name_en']);

        if (ListingType::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => ['The listing mode code has already been taken.']]);
        }
        if (ListingType::query()->where('slug', $slug)->exists()) {
            $slug = $slug . '-' . strtolower(Str::random(4));
        }

        $listingType = ListingType::query()->create([
            'code' => $code,
            'slug' => $slug,
            'transaction_mode' => $validated['transaction_mode'],
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'default_price_period' => $validated['default_price_period'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_phase1_enabled' => $request->has('is_phase1_enabled') ? $request->boolean('is_phase1_enabled') : true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Listing mode created successfully.',
                'listing_type' => $listingType,
            ], 201);
        }

        return back()->with('success', "Listing mode '{$listingType->name_en}' created successfully.");
    }

    public function updateListingType(ListingType $listingType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'code' => 'required|string|max:32',
            'slug' => 'nullable|string|max:80',
            'transaction_mode' => 'required|string|in:BUY,RENT,LEASE,STAY,NEW_PROJECTS',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'default_price_period' => 'required|in:total,per_month,per_year,per_night',
            'sort_order' => 'nullable|integer|min:0',
            'is_phase1_enabled' => 'nullable|boolean',
        ]);

        $code = strtoupper(trim($validated['code']));
        $slug = ! empty($validated['slug']) ? Str::slug($validated['slug']) : $listingType->slug;

        if (ListingType::query()->where('code', $code)->where('id', '!=', $listingType->id)->exists()) {
            throw ValidationException::withMessages(['code' => ['The listing mode code has already been taken.']]);
        }

        $listingType->update([
            'code' => $code,
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'transaction_mode' => $validated['transaction_mode'],
            'default_price_period' => $validated['default_price_period'],
            'sort_order' => (int) ($validated['sort_order'] ?? $listingType->sort_order),
            'is_phase1_enabled' => $request->has('is_phase1_enabled') ? $request->boolean('is_phase1_enabled') : $listingType->is_phase1_enabled,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Listing mode updated successfully.',
                'listing_type' => $listingType->fresh(),
            ]);
        }

        return back()->with('success', "Listing mode '{$listingType->name_en}' updated successfully.");
    }

    public function toggleListingType(ListingType $listingType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $listingType->update([
            'is_phase1_enabled' => ! $listingType->is_phase1_enabled,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Listing type status updated.',
                'listing_type' => $listingType->fresh(),
            ]);
        }

        return back()->with('success', 'Listing type status updated.');
    }

    public function destroyListingType(ListingType $listingType, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $hasListings = DB::table('listings')->where('listing_type_id', $listingType->id)->exists();
        if ($hasListings) {
            throw ValidationException::withMessages([
                'listing_type' => ['Cannot delete listing mode referenced by active listings. Please deactivate it instead.'],
            ]);
        }

        $listingType->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Listing mode deleted successfully.']);
        }

        return back()->with('success', 'Listing mode deleted successfully.');
    }

    public function storeAmenity(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'group_key' => 'required|string|max:40',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'slug' => 'required|string|max:80',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = Str::slug($validated['slug'], '_');
        if (PropertyAmenity::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => ['The amenity identifier (slug) has already been taken.']]);
        }

        $amenity = PropertyAmenity::query()->create([
            'group_key' => strtolower(trim($validated['group_key'])),
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Amenity created successfully.',
                'amenity' => $amenity,
            ], 201);
        }

        return back()->with('success', "Amenity '{$amenity->name_en}' created successfully.");
    }

    public function updateAmenity(PropertyAmenity $amenity, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'group_key' => 'required|string|max:40',
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'slug' => 'required|string|max:80',
            'icon' => 'nullable|string|max:64',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = Str::slug($validated['slug'], '_');
        if (PropertyAmenity::query()->where('slug', $slug)->where('id', '!=', $amenity->id)->exists()) {
            throw ValidationException::withMessages(['slug' => ['The amenity identifier (slug) has already been taken.']]);
        }

        $amenity->update([
            'group_key' => strtolower(trim($validated['group_key'])),
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : null,
            'sort_order' => (int) ($validated['sort_order'] ?? $amenity->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $amenity->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Amenity updated successfully.',
                'amenity' => $amenity->fresh(),
            ]);
        }

        return back()->with('success', "Amenity '{$amenity->name_en}' updated successfully.");
    }

    public function toggleAmenity(PropertyAmenity $amenity, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $amenity->update([
            'is_active' => ! $amenity->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Amenity status updated.',
                'amenity' => $amenity->fresh(),
            ]);
        }

        return back()->with('success', 'Amenity status updated.');
    }

    public function destroyAmenity(PropertyAmenity $amenity, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $hasProperties = DB::table('property_amenity_pivot')->where('amenity_id', $amenity->id)->exists();
        if ($hasProperties) {
            throw ValidationException::withMessages([
                'amenity' => ['Cannot delete amenity referenced by existing properties. Please deactivate it instead.'],
            ]);
        }

        $amenity->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Amenity deleted successfully.']);
        }

        return back()->with('success', 'Amenity deleted successfully.');
    }

    public function storeFeature(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'slug' => 'required|string|max:80',
            'input_type' => 'required|string|in:text,number,boolean,select,multi_select',
            'options_json' => 'nullable',
            'unit' => 'nullable|string|max:24',
            'applicable_category_codes' => 'required|array|min:1',
            'applicable_category_codes.*' => 'string|exists:property_categories,code',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = Str::slug($validated['slug'], '_');
        if (PropertyFeature::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => ['The feature key (slug) has already been taken.']]);
        }

        $options = null;
        if (in_array($validated['input_type'], ['select', 'multi_select'], true)) {
            $rawOptions = $request->input('options_json');
            if (is_array($rawOptions)) {
                $options = array_values(array_filter(array_map('trim', $rawOptions)));
            } elseif (is_string($rawOptions)) {
                $options = array_values(array_filter(array_map('trim', explode(',', $rawOptions))));
            }
        }

        $feature = PropertyFeature::query()->create([
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'input_type' => $validated['input_type'],
            'options_json' => $options,
            'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : null,
            'applicable_category_codes' => array_values(array_unique($validated['applicable_category_codes'])),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Dynamic feature created successfully.',
                'feature' => $feature,
            ], 201);
        }

        return back()->with('success', "Dynamic feature '{$feature->name_en}' created successfully.");
    }

    public function updateFeature(PropertyFeature $feature, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $validated = $request->validate([
            'name_en' => 'required|string|max:120',
            'name_am' => 'nullable|string|max:120',
            'name_om' => 'nullable|string|max:120',
            'slug' => 'required|string|max:80',
            'input_type' => 'required|string|in:text,number,boolean,select,multi_select',
            'options_json' => 'nullable',
            'unit' => 'nullable|string|max:24',
            'applicable_category_codes' => 'required|array|min:1',
            'applicable_category_codes.*' => 'string|exists:property_categories,code',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = Str::slug($validated['slug'], '_');
        if (PropertyFeature::query()->where('slug', $slug)->where('id', '!=', $feature->id)->exists()) {
            throw ValidationException::withMessages(['slug' => ['The feature key (slug) has already been taken.']]);
        }

        $options = null;
        if (in_array($validated['input_type'], ['select', 'multi_select'], true)) {
            $rawOptions = $request->input('options_json');
            if (is_array($rawOptions)) {
                $options = array_values(array_filter(array_map('trim', $rawOptions)));
            } elseif (is_string($rawOptions)) {
                $options = array_values(array_filter(array_map('trim', explode(',', $rawOptions))));
            }
        }

        $feature->update([
            'slug' => $slug,
            'name_en' => trim($validated['name_en']),
            'name_am' => ! empty($validated['name_am']) ? trim($validated['name_am']) : null,
            'name_om' => ! empty($validated['name_om']) ? trim($validated['name_om']) : null,
            'input_type' => $validated['input_type'],
            'options_json' => $options,
            'unit' => ! empty($validated['unit']) ? trim($validated['unit']) : null,
            'applicable_category_codes' => array_values(array_unique($validated['applicable_category_codes'])),
            'sort_order' => (int) ($validated['sort_order'] ?? $feature->sort_order),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $feature->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Dynamic feature updated successfully.',
                'feature' => $feature->fresh(),
            ]);
        }

        return back()->with('success', "Dynamic feature '{$feature->name_en}' updated successfully.");
    }

    public function toggleFeature(PropertyFeature $feature, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $feature->update([
            'is_active' => ! $feature->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Feature status updated.',
                'feature' => $feature->fresh(),
            ]);
        }

        return back()->with('success', 'Feature status updated.');
    }

    public function destroyFeature(PropertyFeature $feature, Request $request): JsonResponse|RedirectResponse
    {
        $this->checkTaxonomyAuthorization($request);

        $hasValues = DB::table('property_feature_values')->where('feature_id', $feature->id)->exists();
        if ($hasValues) {
            throw ValidationException::withMessages([
                'feature' => ['Cannot delete dynamic feature referenced by properties. Please deactivate it instead.'],
            ]);
        }

        $feature->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dynamic feature deleted successfully.']);
        }

        return back()->with('success', 'Dynamic feature deleted successfully.');
    }
}
