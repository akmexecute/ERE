<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportTicket extends Model
{
    use HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'ticket_code',
        'user_id',
        'contract_id',
        'offer_id',
        'against_user_id',
        'property_id',
        'transaction_reference',
        'seller_snapshot_json',
        'evidence_json',
        'category',
        'priority',
        'subject',
        'description',
        'assigned_staff_id',
        'status',
    ];

    protected $casts = [
        'seller_snapshot_json' => 'array',
        'evidence_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Developer\Models\DeveloperSalesContract::class, 'contract_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Offer\Models\PropertyOffer::class, 'offer_id');
    }

    public function againstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Property\Models\Property::class, 'property_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
