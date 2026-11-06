<?php

declare(strict_types=1);

namespace App\Domains\VirtualTour\Models;

use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\VirtualTour\Enums\TourType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PropertyVirtualTour extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'property_virtual_tours';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'property_id',
        'created_by_user_id',
        'title',
        'description',
        'tour_type',
        'external_tour_url',
        'is_published',
        'published_at',
        'views_count',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'tour_type' => TourType::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(PropertyTourScene::class, 'tour_id')->orderBy('sort_order');
    }

    public function defaultScene(): ?PropertyTourScene
    {
        return $this->scenes()->where('is_default_entry', true)->first()
            ?? $this->scenes()->first();
    }
}

