<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use App\Domains\Host\Models\Host;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class HostPayoutDestination extends Model
{
    use Auditable, HasUuids;

    protected $table = 'host_payout_destinations';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'host_id',
        'user_id',
        'channel',
        'institution_name',
        'account_holder_name',
        'account_number_encrypted',
        'account_number_masked',
        'branch_name',
        'swift_code',
        'is_verified',
        'verified_at',
        'verified_by_user_id',
        'is_primary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function setAccountNumber(string $plainNumber): void
    {
        $this->account_number_encrypted = Crypt::encryptString($plainNumber);
        $clean = preg_replace('/\s+/', '', $plainNumber) ?? '';
        $length = strlen($clean);
        $this->account_number_masked = $length > 4
            ? str_repeat('*', $length - 4) . substr($clean, -4)
            : '****' . $clean;
    }

    public function getDecryptedAccountNumber(): string
    {
        return Crypt::decryptString($this->account_number_encrypted);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class, 'host_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(HostPayout::class, 'payout_destination_id');
    }
}

