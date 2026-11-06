<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\RequestVerificationAction;
use App\Domains\Verification\Models\Verification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SubmitNationalIdVerificationAction
{
    public function __construct(
        private UploadDocumentVersionAction $uploadDocAction,
        private RequestVerificationAction $requestVerificationAction
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(
        User $user,
        string $nationalIdNumber,
        string $nationalIdType,
        UploadedFile $frontImage,
        ?UploadedFile $backImage = null
    ): User {
        $cleanId = trim($nationalIdNumber);
        if (mb_strlen($cleanId) < 4 || mb_strlen($cleanId) > 64) {
            throw ValidationException::withMessages([
                'national_id_number' => ['The National ID or Fayda number must be between 4 and 64 characters.'],
            ]);
        }

        $validTypes = ['fayda', 'national_id', 'kebele_id', 'fayda_digital_qr', 'passport'];
        $cleanType = strtolower(trim($nationalIdType));
        if (! in_array($cleanType, $validTypes, true)) {
            $cleanType = 'fayda';
        }

        return DB::transaction(function () use ($user, $cleanId, $cleanType, $frontImage, $backImage): User {
            // 1. Upload Front Document Version into Private Vault
            $frontDoc = $this->uploadDocAction->execute(
                uploader: $user,
                documentableType: 'user',
                documentableId: $user->id,
                documentTypeCode: 'national_id_front',
                title: 'National ID / Fayda (Front)',
                file: $frontImage,
                accessLevel: DocumentAccessLevel::VerificationOnly,
                changeNotes: 'Submitted for Level 1 National ID Verification'
            );

            // 2. Upload Back Document Version (if provided or required)
            $backDoc = null;
            if ($backImage !== null) {
                $backDoc = $this->uploadDocAction->execute(
                    uploader: $user,
                    documentableType: 'user',
                    documentableId: $user->id,
                    documentTypeCode: 'national_id_back',
                    title: 'National ID / Fayda (Back)',
                    file: $backImage,
                    accessLevel: DocumentAccessLevel::VerificationOnly,
                    changeNotes: 'Submitted for Level 1 National ID Verification'
                );
            }

            // 3. Update User Attributes
            $user->update([
                'national_id_number' => $cleanId,
                'national_id_type' => $cleanType,
                'id_front_document_id' => $frontDoc->id,
                'id_back_document_id' => $backDoc?->id,
            ]);

            // 4. Create / Attach to Verification Request
            $versionIds = array_filter([
                $frontDoc->versions()->first()?->id,
                $backDoc?->versions()->first()?->id,
            ]);

            $this->requestVerificationAction->execute(
                requester: $user,
                target: $user,
                verifiableId: $user->id,
                requestedLevel: 1,
                documentVersionIds: array_values($versionIds)
            );

            return $user->fresh(['idFrontDocument', 'idBackDocument']);
        });
    }
}

