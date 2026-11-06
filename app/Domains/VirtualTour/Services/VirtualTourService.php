<?php

declare(strict_types=1);

namespace App\Domains\VirtualTour\Services;

use App\Domains\Property\Models\Property;
use App\Domains\Subscription\Services\SubscriptionEntitlementService;
use App\Domains\User\Models\User;
use App\Domains\VirtualTour\Enums\TourType;
use App\Domains\VirtualTour\Models\PropertyTourHotspot;
use App\Domains\VirtualTour\Models\PropertyTourScene;
use App\Domains\VirtualTour\Models\PropertyVirtualTour;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class VirtualTourService
{
    public function __construct(
        private SubscriptionEntitlementService $entitlementService
    ) {}

    /**
     * Create a virtual tour for a property, enforcing owner entitlement.
     */
    public function createTour(User $actor, Property $property, array $data): PropertyVirtualTour
    {
        // 1. Authorization: user must own property or be an administrator
        if ($property->owner_id !== $actor->id && ! $actor->hasAnyRole(['super_admin', 'platform_admin', 'property_admin'])) {
            throw new DomainException('Unauthorized: You can only create virtual tours for properties you own.');
        }

        // 2. Subscription Entitlement Gate
        $this->entitlementService->validateVirtualTourCreation($actor);

        return PropertyVirtualTour::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_id' => $property->id,
            'created_by_user_id' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'tour_type' => $data['tour_type'] ?? TourType::EquirectangularPanorama->value,
            'external_tour_url' => $data['external_tour_url'] ?? null,
            'is_published' => $data['is_published'] ?? true,
            'published_at' => ($data['is_published'] ?? true) ? now() : null,
        ]);
    }

    /**
     * Add a room scene with a 360° panorama image.
     */
    public function addScene(User $actor, PropertyVirtualTour $tour, array $data, UploadedFile $panoramaFile): PropertyTourScene
    {
        $this->validateTourOwnership($actor, $tour);

        // Upload and store panorama image
        $filename = sprintf('pano_%s_%s.%s', Str::uuid(), time(), $panoramaFile->getClientOriginalExtension());
        $path = $panoramaFile->storeAs("virtual_tours/{$tour->id}", $filename, 'public');

        $isFirstScene = $tour->scenes()->count() === 0;

        return PropertyTourScene::query()->create([
            'tour_id' => $tour->id,
            'room_name' => $data['room_name'],
            'panorama_image_path' => $path,
            'sort_order' => $data['sort_order'] ?? ($tour->scenes()->count() + 1),
            'initial_pitch' => (float) ($data['initial_pitch'] ?? 0.0),
            'initial_yaw' => (float) ($data['initial_yaw'] ?? 0.0),
            'initial_zoom' => (float) ($data['initial_zoom'] ?? 100.0),
            'is_default_entry' => $data['is_default_entry'] ?? $isFirstScene,
        ]);
    }

    /**
     * Add a room-to-room navigation hotspot.
     */
    public function addHotspot(User $actor, PropertyTourScene $scene, array $data): PropertyTourHotspot
    {
        $tour = $scene->tour;
        $this->validateTourOwnership($actor, $tour);

        $targetScene = PropertyTourScene::query()->findOrFail($data['target_scene_id']);
        if ($targetScene->tour_id !== $tour->id) {
            throw new DomainException('Invalid hotspot destination: target room must belong to the same property virtual tour.');
        }

        $pitch = (float) $data['pitch'];
        $yaw = (float) $data['yaw'];

        if ($pitch < -90.0 || $pitch > 90.0) {
            throw new DomainException('Pitch must be between -90 and 90 degrees.');
        }

        if ($yaw < -180.0 || $yaw > 180.0) {
            throw new DomainException('Yaw must be between -180 and 180 degrees.');
        }

        return PropertyTourHotspot::query()->create([
            'scene_id' => $scene->id,
            'target_scene_id' => $targetScene->id,
            'title' => $data['title'] ?? "To {$targetScene->room_name}",
            'pitch' => $pitch,
            'yaw' => $yaw,
            'icon' => $data['icon'] ?? 'arrow_forward',
        ]);
    }

    /**
     * Build viewer configuration payload for 360° viewer component.
     * Publicly accessible by any visitor without a subscription!
     */
    public function getViewerPayload(PropertyVirtualTour $tour): array
    {
        $tour->loadMissing(['scenes.hotspots.targetScene']);
        $scenes = $tour->scenes;

        $defaultScene = $tour->defaultScene() ?? $scenes->first();

        $scenesConfig = [];
        foreach ($scenes as $scene) {
            $hotspotsConfig = [];
            foreach ($scene->hotspots as $hotspot) {
                $hotspotsConfig[] = [
                    'id' => $hotspot->id,
                    'pitch' => (float) $hotspot->pitch,
                    'yaw' => (float) $hotspot->yaw,
                    'type' => 'scene',
                    'text' => $hotspot->title,
                    'sceneId' => 'scene_' . $hotspot->target_scene_id,
                    'targetRoomName' => $hotspot->targetScene?->room_name,
                ];
            }

            $scenesConfig['scene_' . $scene->id] = [
                'id' => $scene->id,
                'title' => $scene->room_name,
                'panorama' => asset('storage/' . $scene->panorama_image_path),
                'pitch' => (float) $scene->initial_pitch,
                'yaw' => (float) $scene->initial_yaw,
                'hfov' => (float) $scene->initial_zoom,
                'hotSpots' => $hotspotsConfig,
            ];
        }

        return [
            'tour' => [
                'id' => $tour->id,
                'uuid' => $tour->uuid,
                'title' => $tour->title,
                'tour_type' => $tour->tour_type->value,
                'external_url' => $tour->external_tour_url,
                'default_scene_id' => $defaultScene ? 'scene_' . $defaultScene->id : null,
            ],
            'scenes' => $scenesConfig,
        ];
    }

    private function validateTourOwnership(User $actor, PropertyVirtualTour $tour): void
    {
        if ($tour->created_by_user_id !== $actor->id &&
            $tour->property?->owner_id !== $actor->id &&
            ! $actor->hasAnyRole(['super_admin', 'platform_admin', 'property_admin'])
        ) {
            throw new DomainException('Unauthorized: You do not own this virtual tour.');
        }
    }
}

