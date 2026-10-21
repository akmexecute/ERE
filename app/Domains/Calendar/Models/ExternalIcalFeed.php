<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Models;

use App\Domains\Stay\Models\AccommodationUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExternalIcalFeed extends Model
{
    protected $fillable = [
        'accommodation_unit_id',
        'channel_name',
        'import_ical_url',
        'export_token',
        'last_synced_at',
        'last_sync_status',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'accommodation_unit_id');
    }
}
