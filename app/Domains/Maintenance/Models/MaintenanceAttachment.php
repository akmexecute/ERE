<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MaintenanceAttachment extends Model
{
    use HasUuids;

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'maintenance_request_id',
        'uploaded_by_user_id',
        'storage_disk',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'caption',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
