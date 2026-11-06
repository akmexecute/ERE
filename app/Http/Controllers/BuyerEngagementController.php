<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Engagement\Models\CollectionItem;
use App\Domains\Engagement\Models\Favorite;
use App\Domains\Engagement\Models\PriceAlert;
use App\Domains\Engagement\Models\SavedCollection;
use App\Domains\Engagement\Models\SavedSearch;
use App\Domains\Listing\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BuyerEngagementController extends Controller
{
    // ==========================================
    // 1. Favorites (SRS Section 33)
    // ==========================================

    public function listFavorites(Request $request): View|JsonResponse
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->with(['listing.property.city', 'listing.property.subcity', 'listing.property.images'])
            ->orderByDesc('created_at')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($favorites);
        }

        return view('buyer.favorites', ['favorites' => $favorites]);
    }

    public function addFavorite(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
        ]);

        $listing = Listing::query()->with('property')->findOrFail($validated['listing_id']);

        $favorite = Favorite::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'listing_id' => $listing->id,
            ],
            [
                'property_id' => $listing->property_id,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Listing added to favorites.', 'favorite' => $favorite], 201);
        }

        return back()->with('success', 'Listing added to favorites.');
    }

    public function removeFavorite(Listing $listing, Request $request): JsonResponse|RedirectResponse
    {
        Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('listing_id', $listing->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Listing removed from favorites.']);
        }

        return back()->with('success', 'Listing removed from favorites.');
    }

    // ==========================================
    // 2. Private Collections (SRS Section 34)
    // ==========================================

    public function listCollections(Request $request): View|JsonResponse
    {
        $collections = SavedCollection::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($collections);
        }

        return view('buyer.collections.index', ['collections' => $collections]);
    }

    public function storeCollection(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_private' => ['nullable', 'boolean'],
        ]);

        $collection = SavedCollection::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Collection created.', 'collection' => $collection], 201);
        }

        return back()->with('success', 'Collection created successfully.');
    }

    public function showCollection(string $uuid, Request $request): View|JsonResponse
    {
        $collection = SavedCollection::query()
            ->where('uuid', $uuid)
            ->with(['items.listing.property.city', 'items.listing.property.subcity', 'items.listing.property.images'])
            ->firstOrFail();

        // Enforce ownership: only owner or public collection can be viewed
        if ($collection->is_private && $collection->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('This private collection belongs to another user.');
        }

        if ($request->wantsJson()) {
            return response()->json($collection);
        }

        return view('buyer.collections.show', ['collection' => $collection]);
    }

    public function updateCollection(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $collection = SavedCollection::query()->where('uuid', $uuid)->firstOrFail();
        if ($collection->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized to modify this collection.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_private' => ['nullable', 'boolean'],
        ]);

        $collection->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Collection updated.', 'collection' => $collection]);
        }

        return back()->with('success', 'Collection updated.');
    }

    public function destroyCollection(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $collection = SavedCollection::query()->where('uuid', $uuid)->firstOrFail();
        if ($collection->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized to delete this collection.');
        }

        $collection->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Collection deleted.']);
        }

        return back()->with('success', 'Collection deleted.');
    }

    public function addCollectionItem(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $collection = SavedCollection::query()->where('uuid', $uuid)->firstOrFail();
        if ($collection->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized to add items to this collection.');
        }

        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'personal_note' => ['nullable', 'string', 'max:300'],
        ]);

        $listing = Listing::query()->with('property')->findOrFail($validated['listing_id']);

        $item = CollectionItem::query()->firstOrCreate(
            [
                'collection_id' => $collection->id,
                'listing_id' => $listing->id,
            ],
            [
                'property_id' => $listing->property_id,
                'personal_note' => $validated['personal_note'] ?? null,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Item added to collection.', 'item' => $item], 201);
        }

        return back()->with('success', 'Listing added to collection.');
    }

    public function removeCollectionItem(string $uuid, int $itemId, Request $request): JsonResponse|RedirectResponse
    {
        $collection = SavedCollection::query()->where('uuid', $uuid)->firstOrFail();
        if ($collection->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized.');
        }

        CollectionItem::query()
            ->where('collection_id', $collection->id)
            ->where('id', $itemId)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Item removed from collection.']);
        }

        return back()->with('success', 'Item removed from collection.');
    }

    // ==========================================
    // 3. Saved-Search Alerts (SRS Section 109)
    // ==========================================

    public function listSavedSearches(Request $request): View|JsonResponse
    {
        $searches = SavedSearch::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($searches);
        }

        return view('buyer.saved-searches', ['searches' => $searches]);
    }

    public function storeSavedSearch(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'filters_json' => ['required', 'array'],
            'frequency' => ['required', 'string', 'in:instant,daily,weekly'],
        ]);

        $search = SavedSearch::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'filters_json' => $validated['filters_json'],
            'frequency' => $validated['frequency'],
            'is_active' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Search alert saved.', 'saved_search' => $search], 201);
        }

        return back()->with('success', 'Search alert saved successfully.');
    }

    public function destroySavedSearch(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $search = SavedSearch::query()->where('uuid', $uuid)->firstOrFail();
        if ($search->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized.');
        }

        $search->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Saved search alert deleted.']);
        }

        return back()->with('success', 'Saved search alert deleted.');
    }

    // ==========================================
    // 4. Price & Availability Alerts (SRS Section 110)
    // ==========================================

    public function storePriceAlert(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'notify_price_drop' => ['nullable'],
            'notify_status_change' => ['nullable'],
            'notify_on_price_drop' => ['nullable'],
            'notify_on_status_change' => ['nullable'],
        ]);

        $listing = Listing::query()->with('property')->findOrFail($validated['listing_id']);

        $alert = PriceAlert::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'listing_id' => $listing->id,
            ],
            [
                'property_id' => $listing->property_id,
                'baseline_price_etb' => $listing->price_etb,
                'notify_on_price_drop' => (bool) ($validated['notify_on_price_drop'] ?? ($validated['notify_price_drop'] ?? true)),
                'notify_on_status_change' => (bool) ($validated['notify_on_status_change'] ?? ($validated['notify_status_change'] ?? true)),
                'is_active' => true,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Price alert set.', 'alert' => $alert], 201);
        }

        return back()->with('success', 'Price alert activated.');
    }

    public function destroyPriceAlert(int $id, Request $request): JsonResponse|RedirectResponse
    {
        $alert = PriceAlert::query()->where('id', $id)->firstOrFail();
        if ($alert->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException('Unauthorized.');
        }

        $alert->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Price alert deleted.']);
        }

        return back()->with('success', 'Price alert removed.');
    }
}
