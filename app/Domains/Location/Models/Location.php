<?php

declare(strict_types=1);

namespace App\Domains\Location\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class Location extends Model
{
    public const array LEVELS = [
        'country',
        'region',
        'zone',
        'city',
        'subcity',
        'woreda',
        'kebele',
        'neighborhood',
    ];

    public const array VALID_CHILD_LEVELS = [
        'country' => ['region'],
        'region' => ['zone', 'city'],
        'zone' => ['city', 'woreda'],
        'city' => ['subcity', 'woreda', 'kebele', 'neighborhood'],
        'subcity' => ['woreda', 'kebele', 'neighborhood'],
        'woreda' => ['kebele', 'neighborhood'],
        'kebele' => ['neighborhood'],
        'neighborhood' => [],
    ];

    protected $fillable = [
        'parent_id',
        'level',
        'code',
        'slug',
        'ancestry_path',
        'name_en',
        'name_am',
        'name_om',
        'latitude',
        'longitude',
        'is_active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            $location->validateHierarchy();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en');
    }

    public function localizedName(?string $locale = null): string
    {
        $lang = $locale ?? app()->getLocale();

        return match ($lang) {
            'am' => $this->name_am ?: $this->name_en,
            'om' => $this->name_om ?: $this->name_en,
            default => $this->name_en,
        };
    }

    public function isValidChildLevel(string $childLevel): bool
    {
        $allowed = self::VALID_CHILD_LEVELS[$this->level] ?? [];

        return in_array($childLevel, $allowed, true);
    }

    /**
     * Enforce hierarchy constraints: prevent self-parenting, validate parent-child levels, and circular cycles.
     */
    public function validateHierarchy(): void
    {
        if (! in_array($this->level, self::LEVELS, true)) {
            throw new InvalidArgumentException("Invalid administrative level: '{$this->level}'.");
        }

        if ($this->parent_id !== null && $this->id !== null && $this->parent_id === $this->id) {
            throw new DomainException("Self-parenting is not permitted. Location {$this->code} cannot be its own parent.");
        }

        if ($this->parent_id !== null) {
            $parent = $this->parent ?? self::find($this->parent_id);
            if ($parent && ! $parent->isValidChildLevel($this->level)) {
                throw new DomainException("Level '{$this->level}' cannot be a direct child of parent level '{$parent->level}'.");
            }
        }
    }

    /**
     * Dynamically resolve the national root country record without assuming database ID 1.
     */
    public static function getRootCountry(): ?self
    {
        return self::query()
            ->where('slug', 'ethiopia')
            ->orWhere('code', 'ETH')
            ->orWhere('level', 'country')
            ->first();
    }

    /**
     * Determine if this location is referenced by any property records across the platform.
     */
    public function isReferenced(): bool
    {
        return $this->referencedPropertiesCount() > 0;
    }

    /**
     * Count properties linked to this location at any hierarchy level.
     */
    public function referencedPropertiesCount(): int
    {
        if (! $this->id) {
            return 0;
        }

        return (int) \Illuminate\Support\Facades\DB::table('properties')
            ->where(function ($q): void {
                $q->where('region_id', $this->id)
                    ->orWhere('city_id', $this->id)
                    ->orWhere('subcity_id', $this->id)
                    ->orWhere('woreda_id', $this->id)
                    ->orWhere('kebele_id', $this->id)
                    ->orWhere('neighborhood_id', $this->id);
            })
            ->count();
    }
}
