<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Property\Models\Property;
use App\Domains\Subscription\Services\SubscriptionEntitlementService;
use App\Domains\VirtualTour\Models\PropertyTourHotspot;
use App\Domains\VirtualTour\Models\PropertyTourScene;
use App\Domains\VirtualTour\Models\PropertyVirtualTour;
use App\Domains\VirtualTour\Services\VirtualTourService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VirtualTourController extends Controller
{
    public function __construct(
        private readonly VirtualTourService $virtualTourService,
        private readonly SubscriptionEntitlementService $entitlementService
    ) {}

    /**
     * Owner Tour Management Dashboard.
     */
    public function manage(Request $request, Property $property): View|RedirectResponse
    {
        $user = $request->user();
        if ($property->owner_id !== $user->id && ! $user->hasAnyRole(['super_admin', 'platform_admin', 'property_admin'])) {
            abort(403, 'Unauthorized to manage virtual tours for this property.');
        }

        $tour = $property->virtualTour;

        // If creating a brand new tour, check active subscription entitlement
        if (! $tour) {
            try {
                $this->entitlementService->validateVirtualTourCreation($user);
            } catch (DomainException $e) {
                return redirect()->route('subscriptions.index')
                    ->with('error', $e->getMessage() . ' Upgrade to Agent Pro, Agency, or Developer to create 360° virtual tours.');
            }
        }

        if ($tour) {
            $tour->load(['scenes.hotspots.targetScene']);
        }

        return view('virtual-tours.manage', [
            'property' => $property,
            'tour' => $tour,
        ]);
    }

    /**
     * Create or update virtual tour record.
     */
    public function storeTour(Request $request, Property $property): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'tour_type' => ['nullable', 'string'],
            'external_tour_url' => ['nullable', 'url', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $tour = $this->virtualTourService->createTour($request->user(), $property, $validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'tour' => $tour], 201);
        }

        return redirect()->route('virtual-tours.manage', $property)
            ->with('success', 'Virtual tour workspace created. Now add your 360° panorama rooms.');
    }

    /**
     * Upload and add a room scene.
     */
    public function storeScene(Request $request, PropertyVirtualTour $tour): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:80'],
            'panorama_file' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:25600'],
            'initial_pitch' => ['nullable', 'numeric', 'between:-90,90'],
            'initial_yaw' => ['nullable', 'numeric', 'between:-180,180'],
            'initial_zoom' => ['nullable', 'numeric', 'between:40,120'],
            'is_default_entry' => ['nullable', 'boolean'],
        ]);

        $scene = $this->virtualTourService->addScene(
            $request->user(),
            $tour,
            $validated,
            $request->file('panorama_file')
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'scene' => $scene], 201);
        }

        return redirect()->route('virtual-tours.manage', $tour->property_id)
            ->with('success', "Room scene [{$scene->room_name}] uploaded successfully.");
    }

    /**
     * Add navigation hotspot between scenes.
     */
    public function storeHotspot(Request $request, PropertyTourScene $scene): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'target_scene_id' => ['required', 'exists:property_tour_scenes,id'],
            'pitch' => ['required', 'numeric', 'between:-90,90'],
            'yaw' => ['required', 'numeric', 'between:-180,180'],
            'title' => ['nullable', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:40'],
        ]);

        $hotspot = $this->virtualTourService->addHotspot($request->user(), $scene, $validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'hotspot' => $hotspot], 201);
        }

        return redirect()->route('virtual-tours.manage', $scene->tour->property_id)
            ->with('success', 'Navigation hotspot linked.');
    }

    /**
     * Remove a room scene.
     */
    public function destroyScene(Request $request, PropertyTourScene $scene): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $tour = $scene->tour;
        if ($tour->created_by_user_id !== $user->id && ! $user->hasAnyRole(['super_admin', 'platform_admin', 'property_admin'])) {
            abort(403);
        }

        $propertyId = $tour->property_id;
        $scene->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('virtual-tours.manage', $propertyId)
            ->with('success', 'Room scene removed.');
    }

    /**
     * Public 360° Virtual Tour Viewer.
     * Note: Anyone can view published tours without authentication or any subscription!
     */
    public function showPublic(Request $request, string $uuid): View|JsonResponse
    {
        $tour = PropertyVirtualTour::query()->where('uuid', $uuid)->firstOrFail();

        // If not published, only owner or platform admin can view draft
        if (! $tour->is_published) {
            $user = $request->user();
            if (! $user || ($tour->created_by_user_id !== $user->id && ! $user->hasAnyRole(['super_admin', 'platform_admin']))) {
                abort(404, 'Virtual tour not available.');
            }
        }

        $payload = $this->virtualTourService->getViewerPayload($tour);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($payload);
        }

        return view('virtual-tours.public-viewer', [
            'tour' => $tour,
            'payload' => $payload,
        ]);
    }
}

