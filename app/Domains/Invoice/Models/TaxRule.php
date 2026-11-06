<?php

declare(strict_types=1);

namespace App\Domains\Invoice\Models;

use App\Domains\Location\Models\Location;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaxRule extends Model
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'tax_code',
        'name_en',
        'name_am',
        'name_om',
        'service_type',
        'location_id',
        'rate_pct',
        'is_compound',
        'is_inclusive',
        'is_withholding',
        'calculation_order',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_pct' => 'decimal:3',
            'is_compound' => 'boolean',
            'is_inclusive' => 'boolean',
            'is_withholding' => 'boolean',
            'calculation_order' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
