<?php

declare(strict_types=1);

namespace App\Domains\GIS\Models;

use App\Domains\Location\Models\Location;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GisNeighborhoodHeatmap extends Model
{
    use HasFactory;

    protected $table = 'gis_neighborhood_heatmaps';

    protected $fillable = [
        'location_id',
        'transaction_mode',
        'sample_size',
        'avg_price_per_sqm_etb',
        'median_price_per_sqm_etb',
        'min_price_per_sqm_etb',
        'max_price_per_sqm_etb',
        'price_tier',
        'snapshot_date',
        'center_latitude',
        'center_longitude',
        'boundary_geojson',
        'platform_asking_price_indicator',
    ];

    protected function casts(): array
    {
        return [
            'sample_size' => 'integer',
            'avg_price_per_sqm_etb' => 'decimal:2',
            'median_price_per_sqm_etb' => 'decimal:2',
            'min_price_per_sqm_etb' => 'decimal:2',
            'max_price_per_sqm_etb' => 'decimal:2',
            'price_tier' => 'integer',
            'snapshot_date' => 'date',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'boundary_geojson' => 'array',
            'platform_asking_price_indicator' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Map ETB/m² price to codebook price tiers (Tiers 1 - 5).
     */
    public static function tierFromPrice(float $pricePerSqm): int
    {
        return match (true) {
            $pricePerSqm >= 75000.0 => 5,
            $pricePerSqm >= 55000.0 => 4,
            $pricePerSqm >= 40000.0 => 3,
            $pricePerSqm >= 25000.0 => 2,
            default => 1,
        };
    }

    public static function tierColor(int $tier): string
    {
        return match ($tier) {
            5 => '#7f1d1d', // Very High (Deep Red)
            4 => '#c2410c', // High (Orange Red)
            3 => '#d97706', // Moderate High (Amber)
            2 => '#0284c7', // Moderate (Sky Blue)
            default => '#059669', // Affordable / Base (Emerald)
        };
    }

    /**
     * Convert this neighborhood record into a valid GeoJSON Feature.
     */
    public function toGeoJsonFeature(): array
    {
        $geometry = $this->boundary_geojson;

        // If no polygon geometry stored, fallback to Point feature if coordinates exist
        if (empty($geometry) && $this->center_latitude && $this->center_longitude) {
            $geometry = [
                'type' => 'Point',
                'coordinates' => [$this->center_longitude, $this->center_latitude],
            ];
        }

        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => $geometry,
            'properties' => [
                'neighborhood_id' => $this->location_id,
                'name' => $this->location?->name_en ?? "Zone #{$this->location_id}",
                'name_am' => $this->location?->name_am,
                'name_om' => $this->location?->name_om,
                'level' => $this->location?->level ?? 'neighborhood',
                'transaction_mode' => $this->transaction_mode,
                'sample_size' => $this->sample_size,
                'avg_price_per_sqm_etb' => (float) $this->avg_price_per_sqm_etb,
                'median_price_per_sqm_etb' => (float) $this->median_price_per_sqm_etb,
                'min_price_per_sqm_etb' => (float) $this->min_price_per_sqm_etb,
                'max_price_per_sqm_etb' => (float) $this->max_price_per_sqm_etb,
                'price_tier' => $this->price_tier,
                'color' => self::tierColor($this->price_tier),
                'snapshot_date' => $this->snapshot_date?->format('Y-m-d'),
                'platform_asking_price_indicator' => (bool) $this->platform_asking_price_indicator,
                'disclosure' => 'Platform-Derived Asking-Price Indicator: Values reflect unverified asking prices listed on ERE and do not represent official government land registry transaction valuations.',
            ],
        ];
    }
}

