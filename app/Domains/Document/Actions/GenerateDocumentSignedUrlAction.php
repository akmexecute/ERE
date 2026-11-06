<?php

declare(strict_types=1);

namespace App\Domains\Document\Actions;

use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentVersion;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class GenerateDocumentSignedUrlAction
{
    /**
     * Issues a short-lived (5-minute) signed download URL after strict authorization,
     * recording immutable access telemetry to document_access_logs (SRS Sections 28-29 & 144).
     *
     * @return array{
     *     url: string,
     *     expires_at: Carbon,
     *     version_number: int,
     *     original_filename: string,
     *     mime_type: string
     * }
     *
     * @throws AuthorizationException
     */
    public function execute(
        User $requester,
        Document $document,
        ?int $versionNumber = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $this->authorizeAccess($requester, $document);

        $targetVersionNumber = $versionNumber ?? (int) $document->current_version_number;

        /** @var DocumentVersion $version */
        $version = $document->versions()
            ->where('version_number', $targetVersionNumber)
            ->firstOrFail();

        $expiresAt = now()->addMinutes(5);
        $signedUrl = $version->generateTemporarySignedUrl(minutes: 5);

        // Immutable Access Audit Log
        DB::table('document_access_logs')->insert([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'user_id' => $requester->id,
            'action' => 'signed_url_issued',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'created_at' => now(),
        ]);

        return [
            'url' => $signedUrl,
            'expires_at' => $expiresAt,
            'version_number' => $version->version_number,
            'original_filename' => $version->original_filename,
            'mime_type' => $version->mime_type,
        ];
    }

    public function authorizeAccess(User $requester, Document $document): void
    {
        // 1. Public documents
        if ($document->access_level === DocumentAccessLevel::Public) {
            return;
        }

        // 2. Original uploader
        if ($requester->id === $document->uploaded_by) {
            return;
        }

        // 3. System Administrator, Compliance Moderator, or Authorized Staff
        if (method_exists($requester, 'hasAnyRole') && $requester->hasAnyRole([
            'admin', 'moderator', 'system_admin', 'super_admin', 'platform_admin', 'verification_officer', 'property_admin',
        ])) {
            return;
        }

        // 4. Entity ownership inspection
        if ($document->documentable_type === Property::class) {
            $property = Property::query()->find($document->documentable_id);
            if ($property && ($property->owner_id === $requester->id || ($property->company_id && $property->company_id === $requester->company_id))) {
                return;
            }
        } elseif ($document->documentable_type === User::class) {
            if ($document->documentable_id === $requester->id) {
                return;
            }
        }

        throw new AuthorizationException('You are not authorized to access this private document.');
    }
}
