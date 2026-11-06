<?php

declare(strict_types=1);

namespace App\Domains\Localization\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SystemTranslation extends Model
{
    protected $table = 'system_translations';

    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
        'is_reviewed',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_reviewed' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
