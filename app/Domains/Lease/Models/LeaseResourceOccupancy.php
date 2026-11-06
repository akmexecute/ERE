<?php

declare(strict_types=1);

namespace App\Domains\Lease\Models;

use App\Domains\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LeaseResourceOccupancy extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'property_id',
        'resource_id',
        'lease_daterange',
        'status',
        'created_at',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
