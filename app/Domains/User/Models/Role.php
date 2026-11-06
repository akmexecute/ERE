<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'slug',
        'name_en',
        'name_am',
        'name_om',
        'is_staff_role',
    ];

    protected function casts(): array
    {
        return [
            'is_staff_role' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
            ->withPivot('assigned_at');
    }
}
