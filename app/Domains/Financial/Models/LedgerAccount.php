<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use App\Domains\Financial\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LedgerAccount extends Model
{
    protected $table = 'ledger_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id');
    }
}

