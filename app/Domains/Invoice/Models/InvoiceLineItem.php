<?php

declare(strict_types=1);

namespace App\Domains\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceLineItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_etb',
        'line_subtotal_etb',
        'tax_code',
        'tax_name_snapshot',
        'tax_rate_pct',
        'tax_amount_etb',
        'line_total_etb',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_etb' => 'decimal:2',
            'line_subtotal_etb' => 'decimal:2',
            'tax_rate_pct' => 'decimal:3',
            'tax_amount_etb' => 'decimal:2',
            'line_total_etb' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
