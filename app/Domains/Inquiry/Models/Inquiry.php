<?php

declare(strict_types=1);

namespace App\Domains\Inquiry\Models;

use App\Domains\Inquiry\Enums\InquiryStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\Company;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Inquiry extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'inquiry_code',
        'listing_id',
        'property_id',
        'buyer_id',
        'seller_id',
        'company_id',
        'lead_source',
        'status',
        'initial_message',
        'unread_buyer_count',
        'unread_seller_count',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'unread_buyer_count' => 'integer',
            'unread_seller_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'inquiry_id')->orderBy('created_at');
    }
}
