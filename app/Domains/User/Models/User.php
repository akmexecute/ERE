<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

final class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'email',
        'password',
        'profile_photo_path',
        'preferred_locale',
        'phone_privacy',
        'verification_level',
        'status',
        'phone_verified_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'phone_privacy' => PhonePrivacyMode::class,
            'verification_level' => 'integer',
            'phone_verified_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $roleSlug): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('roles.slug', $roleSlug)
            ->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $hasViaRole = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $this->id)
            ->where('permissions.slug', $permissionSlug)
            ->exists();

        if ($hasViaRole) {
            return true;
        }

        return DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $this->id)
            ->where('permissions.slug', $permissionSlug)
            ->exists();
    }

    public function assignRoleBySlug(string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if ($roleId) {
            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $this->id,
                'role_id' => $roleId,
                'assigned_at' => now(),
            ]);
        }
    }
}
