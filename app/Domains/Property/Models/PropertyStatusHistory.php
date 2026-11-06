<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PropertyStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'property_status_histories';

    protected $fillable = [
        'property_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PropertyStatus::class,
            'to_status' => PropertyStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
