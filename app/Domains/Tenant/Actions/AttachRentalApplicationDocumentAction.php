<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\RentalApplicationDocument;
use App\Domains\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AttachRentalApplicationDocumentAction
{
    public function __construct(
        private UploadDocumentVersionAction $uploader,
    ) {}

    public function execute(
        User $actor,
        RentalApplication $application,
        UploadedFile $file,
        string $documentType = 'income_proof',
        ?string $title = null
    ): RentalApplicationDocument {
        $resolvedTitle = $title ?? sprintf(
            '%s Document for Application #%s',
            ucwords(str_replace('_', ' ', $documentType)),
            $application->application_code
        );

        return DB::transaction(function () use (
            $actor,
            $application,
            $file,
            $documentType,
            $resolvedTitle
        ): RentalApplicationDocument {
            // Upload to document vault with verification-only access
            $document = $this->uploader->execute(
                uploader: $actor,
                documentableType: 'rental_application',
                documentableId: $application->id,
                documentTypeCode: strtoupper($documentType),
                title: $resolvedTitle,
                file: $file,
                accessLevel: DocumentAccessLevel::VerificationOnly,
                changeNotes: 'Attached to rental application #' . $application->application_code
            );

            return RentalApplicationDocument::query()->create([
                'uuid' => (string) Str::uuid(),
                'rental_application_id' => $application->id,
                'document_id' => $document->id,
                'document_type' => $documentType,
                'review_status' => 'pending',
                'created_at' => now(),
            ]);
        });
    }
}

