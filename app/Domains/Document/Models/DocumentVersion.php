<?php

declare(strict_types=1);

namespace App\Domains\Document\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class DocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'version_number',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size_bytes',
        'sha256_checksum',
        'uploaded_by',
        'change_notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Generate a temporary signed download URL valid for at most 5 minutes (SRS Sections 28-29 & 144).
     * Cryptographically signed route strictly enforced by Laravel signed middleware.
     */
    public function generateTemporarySignedUrl(int $minutes = 5): string
    {
        $validMinutes = min(max(1, $minutes), 5);

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes($validMinutes),
            [
                'uuid' => $this->document->uuid,
                'version' => $this->version_number,
            ]
        );
    }
}
