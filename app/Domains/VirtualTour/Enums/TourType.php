<?php

declare(strict_types=1);

namespace App\Domains\VirtualTour\Enums;

enum TourType: string
{
    case EquirectangularPanorama = 'equirectangular_panorama';
    case InteractiveWalkthrough = 'interactive_walkthrough';
    case ExternalLink = 'external_link';

    public function label(): string
    {
        return match ($this) {
            self::EquirectangularPanorama => '360° Equirectangular Panorama',
            self::InteractiveWalkthrough => 'Room-to-Room Interactive Walkthrough',
            self::ExternalLink => 'External 3D Matterport / Virtual Tour URL',
        };
    }
}

