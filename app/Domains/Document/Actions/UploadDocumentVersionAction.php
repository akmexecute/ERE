<?php

declare(strict_types=1);

namespace App\Domains\Document\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\Lease\Models\Lease;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class UploadDocumentVersionAction
{
    /**
     * Allowed polymorphic targets for document attachments (SRS Section 27).
     * Prevents arbitrary PHP classes or unvetted model bindings from client inputs.
     */
    private const ALLOWED_MORPH_MAP = [
        'property' => Property::class,
        'user' => User::class,
        'company' => Company::class,
        'listing' => Listing::class,
        'rental_application' => RentalApplication::class,
        'lease' => Lease::class,
        'tenant_profile' => TenantProfile::class,
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

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
        // Validate and normalize polymorphic entity type against allowlist
        $normalizedType = $this->resolveAllowedMorphType($documentableType);

        // Server-side MIME verification: do not trust client-supplied getClientMimeType()
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new InvalidArgumentException('Uploaded document file is not accessible on the server.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $serverMime = $finfo ? finfo_file($finfo, $realPath) : null;
        if ($finfo) {
            finfo_close($finfo);
        }

        $resolvedMime = (string) (($serverMime && $serverMime !== 'application/x-empty') ? $serverMime : ($file->getMimeType() ?: 'application/octet-stream'));
        if (! in_array($resolvedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported document format "%s". Allowed formats: PDF, JPEG, PNG, WebP.',
                $resolvedMime
            ));
        }

        $resolvedAccess = $accessLevel ?? DocumentAccessLevel::VerificationOnly;
        $checksum = hash_file('sha256', $realPath) ?: '';

        // Safe normalized filename for display
        $safeOriginalName = preg_replace('/[^\w\.\-\s]/u', '', $file->getClientOriginalName()) ?: 'document';

        $storedPath = null;
        $disk = 's3_private';

        try {
            return DB::transaction(function () use (
                $uploader,
                $normalizedType,
                $documentableId,
                $documentTypeCode,
                $title,
                $file,
                $existingDocument,
                $resolvedAccess,
                $changeNotes,
                $resolvedMime,
                $safeOriginalName,
                $checksum,
                $disk,
                &$storedPath
            ): Document {
                if ($existingDocument) {
                    /** @var Document $document */
                    $document = Document::query()
                        ->where('id', $existingDocument->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $maxVersion = (int) DB::table('document_versions')
                        ->where('document_id', $document->id)
                        ->max('version_number');

                    $nextVersion = $maxVersion + 1;
                } else {
                    $document = Document::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'documentable_type' => $normalizedType,
                        'documentable_id' => $documentableId,
                        'uploaded_by' => $uploader->id,
                        'document_type_code' => $documentTypeCode,
                        'title' => trim($title),
                        'access_level' => $resolvedAccess->value,
                        'current_version_number' => 1,
                        'verification_status' => 'pending',
                    ]);

                    $nextVersion = 1;
                }

                $extension = strtolower($file->getClientOriginalExtension()) ?: ($resolvedMime === 'application/pdf' ? 'pdf' : 'bin');
                $secureName = sprintf('%s_v%d.%s', $document->uuid, $nextVersion, $extension);
                $folder = sprintf('vault/%s/%s', $documentTypeCode, date('Y/m'));

                $storedPath = $file->storeAs($folder, $secureName, $disk);
                if (! $storedPath) {
                    throw new InvalidArgumentException('Failed to store private document on encrypted vault storage.');
                }

                DB::table('document_versions')->insert([
                    'document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'storage_disk' => $disk,
                    'storage_path' => (string) $storedPath,
                    'original_filename' => $safeOriginalName,
                    'mime_type' => $resolvedMime,
                    'file_size_bytes' => $file->getSize() ?: 0,
                    'sha256_checksum' => $checksum,
                    'uploaded_by' => $uploader->id,
                    'change_notes' => $changeNotes,
                    'created_at' => now(),
                ]);

                $document->update([
                    'current_version_number' => $nextVersion,
                ]);

                return $document->fresh();
            });
        } catch (\Throwable $e) {
            // Compensating action: remove orphaned S3 asset on database or transaction failure
            if ($storedPath && Storage::disk($disk)->exists($storedPath)) {
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (\Throwable) {
                    // Log compensating cleanup failure
                }
            }

            throw $e;
        }
    }

    private function resolveAllowedMorphType(string $type): string
    {
        $lowered = strtolower(trim($type));
        if (isset(self::ALLOWED_MORPH_MAP[$lowered])) {
            return self::ALLOWED_MORPH_MAP[$lowered];
        }

        foreach (self::ALLOWED_MORPH_MAP as $class) {
            if ($type === $class || $lowered === strtolower(class_basename($class))) {
                return $class;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Disallowed documentable type "%s". Permitted types: property, user, company, listing.',
            $type
        ));
    }
}
