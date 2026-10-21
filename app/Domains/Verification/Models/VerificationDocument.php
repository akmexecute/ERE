<?php

declare(strict_types=1);

namespace App\Domains\Verification\Models;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerificationDocument extends Model
{
    protected $fillable = [
        'verification_id',
        'document_id',
        'document_version_id',
        'is_verified',
        'officer_remark',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class, 'verification_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }
}
