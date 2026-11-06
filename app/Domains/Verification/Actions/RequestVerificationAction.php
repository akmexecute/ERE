<?php

declare(strict_types=1);

namespace App\Domains\Verification\Actions;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentVersion;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\Verification\Models\Verification;
use App\Domains\Verification\Models\VerificationDocument;
use App\Models\Company;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RequestVerificationAction
{
    private const ALLOWED_TARGETS = [
        'property' => Property::class,
        'user' => User::class,
        'company' => Company::class,
        Property::class => Property::class,
        User::class => User::class,
        Company::class => Company::class,
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Model|string  $targetOrType
     * @param  int|null  $verifiableId
     * @param  int  $requestedLevel
     * @param  array<int, int>  $documentVersionIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $requester,
        mixed $target,
        ?int $verifiableId = null,
        int $requestedLevel = 1,
        array $documentVersionIds = []
    ): Verification {
        if ($target instanceof \Illuminate\Database\Eloquent\Model) {
            $targetModel = $target;
            $targetClass = get_class($target);
            $verifiableId = (int) $target->getKey();
        } else {
            $verifiableType = (string) $target;
            $targetClass = self::ALLOWED_TARGETS[$verifiableType] ?? null;

            if ($targetClass === null) {
                throw new InvalidArgumentException("Unsupported verification target type: {$verifiableType}.");
            }

            $targetModel = $targetClass::query()->find($verifiableId);
            if (! $targetModel) {
                throw new InvalidArgumentException("Verification target record #{$verifiableId} does not exist.");
            }
        }

        // Authorize requester against target
        $this->authorizeRequester($requester, $targetModel);

        if ($requestedLevel < 1 || $requestedLevel > 5) {
            throw ValidationException::withMessages([
                'requested_level' => ['Requested verification level must be between 1 and 5.'],
            ]);
        }

        return DB::transaction(function () use ($requester, $targetClass, $verifiableId, $requestedLevel, $documentVersionIds): Verification {
            $verification = Verification::query()->create([
                'uuid' => (string) Str::uuid(),
                'verifiable_type' => $targetClass,
                'verifiable_id' => $verifiableId,
                'requested_by_user_id' => $requester->id,
                'requested_level' => $requestedLevel,
                'approved_level' => 0,
                'status' => 'pending',
                'badge_code' => null,
            ]);

            // Validate and attach supporting document versions
            if ($documentVersionIds !== []) {
                $versions = DocumentVersion::query()
                    ->whereIn('id', $documentVersionIds)
                    ->with('document')
                    ->get();

                foreach ($versions as $ver) {
                    $doc = $ver->document;
                    if (! $doc) {
                        continue;
                    }

                    // Security: Verify document belongs to target or was uploaded by requester
                    $docTargetMatches = ($doc->documentable_type === $targetClass && (int) $doc->documentable_id === $verifiableId);
                    $docUploaderMatches = ((int) $doc->uploaded_by === $requester->id);

                    if (! $docTargetMatches && ! $docUploaderMatches) {
                        throw new AuthorizationException("Document version #{$ver->id} does not belong to the verification target.");
                    }

                    VerificationDocument::query()->firstOrCreate([
                        'verification_id' => $verification->id,
                        'document_version_id' => $ver->id,
                    ], [
                        'document_id' => $doc->id,
                        'is_verified' => false,
                    ]);
                }
            }

            return $verification->fresh(['documents']);
        });
    }

    private function authorizeRequester(User $requester, mixed $target): void
    {
        // Admin or staff override
        if (method_exists($requester, 'hasAnyRole') && $requester->hasAnyRole(['admin', 'system_admin', 'super_admin', 'platform_admin'])) {
            return;
        }

        if ($target instanceof User) {
            if ($target->id !== $requester->id) {
                throw new AuthorizationException('You can only request verification for your own account.');
            }
        } elseif ($target instanceof Property) {
            $isOwner = (int) $target->owner_id === $requester->id;
            $isCompanyAgent = $target->company_id && (int) $target->company_id === (int) $requester->company_id;

            if (! $isOwner && ! $isCompanyAgent) {
                throw new AuthorizationException('You are not authorized to request verification for this property.');
            }
        } elseif ($target instanceof Company) {
            $isCompanyAdmin = (int) $requester->company_id === (int) $target->id;
            if (! $isCompanyAdmin) {
                throw new AuthorizationException('You are not authorized to request verification for this company.');
            }
        }
    }
}
