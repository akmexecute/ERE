<?php

declare(strict_types=1);

namespace App\Domains\Document\Actions;

use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UploadDocumentVersionAction
{
    public function execute(
        User $uploader,
        string $documentableType,
        int $documentableId,
        string $documentTypeCode,
        string $title,
        UploadedFile $file,
        ?Document $existingDocument = null,
        ?DocumentAccessLevel $accessLevel = null,
        ?string $changeNotes = null,
    ): Document {
        // SRS Section 28: Sensitive documents must never be public by default
        $resolvedAccess = $accessLevel ?? DocumentAccessLevel::VerificationOnly;

        return DB::transaction(function () use (
            $uploader,
            $documentableType,
            $documentableId,
            $documentTypeCode,
            $title,
            $file,
            $existingDocument,
            $resolvedAccess,
            $changeNotes
        ): Document {
            $document = $existingDocument ?? Document::query()->create([
                'uuid' => (string) Str::uuid(),
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'uploaded_by' => $uploader->id,
                'document_type_code' => $documentTypeCode,
                'title' => $title,
                'access_level' => $resolvedAccess->value,
                'current_version_number' => 1,
                'verification_status' => 'pending',
            ]);

            $nextVersion = $existingDocument
                ? ((int) $existingDocument->current_version_number) + 1
                : 1;

            $secureName = sprintf('%s_v%d.%s', $document->uuid, $nextVersion, $file->extension() ?: 'pdf');
            $storagePath = $file->storeAs("vault/{$documentTypeCode}/".date('Y/m'), $secureName, 's3_private');
            $checksum = hash_file('sha256', $file->getRealPath()) ?: '';

            DB::table('document_versions')->insert([
                'document_id' => $document->id,
                'version_number' => $nextVersion,
                'storage_disk' => 's3_private',
                'storage_path' => (string) $storagePath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: 'application/pdf',
                'file_size_bytes' => $file->getSize() ?: 0,
                'sha256_checksum' => $checksum,
                'uploaded_by' => $uploader->id,
                'change_notes' => $changeNotes,
                'created_at' => now(),
            ]);

            if ($existingDocument) {
                $document->update(['current_version_number' => $nextVersion]);
            }

            return $document->fresh();
        });
    }
}
