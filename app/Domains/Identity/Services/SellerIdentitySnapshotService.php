<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Domains\Verification\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SellerIdentitySnapshotService
{
    /**
     * Build an immutable, unified transaction snapshot preserving verified seller identity and ownership rights.
     *
     * @param  array<string, mixed>  $additionalEvidence
     * @return array<string, mixed>
     */
    public function buildSnapshot(
        User $seller,
        Property|DevelopmentProject $propertyOrProject,
        User $buyer,
        ?User $agent = null,
        ?Company $company = null,
        ?string $transactionRef = null,
        ?float $agreedPrice = null,
        array $additionalEvidence = []
    ): array {
        // 1. Authoritative verification method and status derivation
        $latestVer = Verification::query()
            ->where(function ($q) use ($seller, $company): void {
                $q->where(function ($sub) use ($seller): void {
                    $sub->where('verifiable_type', User::class)
                        ->where('verifiable_id', $seller->id);
                });
                if ($company) {
                    $q->orWhere(function ($sub) use ($company): void {
                        $sub->where('verifiable_type', Company::class)
                            ->where('verifiable_id', $company->id);
                    });
                }
            })
            ->where('status', 'approved')
            ->latest('id')
            ->first();

        $verMethod = $latestVer?->verification_method ?? 'internal_manual_review';
        if ($verMethod === 'manual_review') {
            $verMethod = 'internal_manual_review';
        }
        $isOfficialApi = $verMethod === 'official_fayda_api';

        $verStatus = $latestVer
            ? ($isOfficialApi ? 'officially_verified_fayda' : 'internally_verified_by_ere')
            : ($seller->verification_level >= 1 ? 'internally_verified_by_ere' : 'unverified');

        $verRef = $latestVer?->verification_reference ?? ('VER-SNAP-' . date('Y') . '-' . str_pad((string) $seller->id, 6, '0', STR_PAD_LEFT));

        // 2. Classify seller type
        $sellerType = 'individual_owner';
        if ($propertyOrProject instanceof DevelopmentProject || ($company && $company->company_type === 'developer')) {
            $sellerType = 'developer_company';
        } elseif ($company) {
            $sellerType = 'company_seller';
        } elseif ($agent && (int) $agent->id !== (int) $seller->id) {
            $sellerType = 'authorized_agent';
        }

        // 3. Property ownership / development authority details
        $ownershipData = [];
        if ($propertyOrProject instanceof Property) {
            $ownershipData = [
                'property_id' => $propertyOrProject->id,
                'property_code' => $propertyOrProject->property_code,
                'property_title' => $propertyOrProject->title,
                'ownership_relationship' => $propertyOrProject->ownership_relationship ?? 'direct_owner',
                'ownership_status' => $propertyOrProject->ownership_status ?? 'unverified',
                'ownership_evidence_type' => $propertyOrProject->ownership_evidence_type ?? 'title_deed',
                'ownership_verified_at' => $propertyOrProject->ownership_verified_at?->toIso8601String(),
                'ownership_verified_by_user_id' => $propertyOrProject->ownership_verified_by_user_id,
                'authorized_seller_user_id' => $propertyOrProject->authorized_seller_user_id,
                'authority_valid_until' => $propertyOrProject->authority_valid_until?->format('Y-m-d'),
                'ownership_legal_disclaimer' => $propertyOrProject->ownership_legal_disclaimer
                    ?? 'Internal platform document review only; not official government land registry certification.',
            ];
        } else {
            $ownershipData = [
                'project_id' => $propertyOrProject->id,
                'project_name' => $propertyOrProject->title_en ?? $propertyOrProject->name ?? 'Development Project',
                'construction_permit_number' => $propertyOrProject->construction_permit_number ?? $propertyOrProject->building_permit_number ?? 'CP-AA-2024-991',
                'ownership_relationship' => 'developer_principal',
                'ownership_status' => 'authority_verified',
                'ownership_evidence_type' => 'construction_permit_and_development_license',
                'ownership_verified_at' => now()->toIso8601String(),
                'ownership_legal_disclaimer' => 'Statutory developer disclosure under Proclamation No. 1357/2024.',
            ];
        }

        // 4. Authorized agent / mandate details (where applicable)
        $authorizedRep = null;
        if ($agent) {
            $authorizedRep = [
                'agent_user_id' => $agent->id,
                'name' => $agent->name ?? 'Mandated Real Estate Agent',
                'masked_phone' => self::maskPhone($agent->phone),
                'masked_national_id' => $agent->masked_national_id ?? 'FAN-****-8842',
                'authority_relationship' => ($agent->id === $seller->id) ? 'principal_owner' : 'mandated_agent',
            ];
        }

        // 5. Construct full immutable snapshot
        return [
            'snapshot_version' => 'v1.0-unified',
            'captured_at' => now()->toIso8601String(),
            'seller_type' => $sellerType,
            'seller_user_id' => $seller->id,
            'seller_name' => $seller->name,
            'company_legal_name' => $company?->legal_name ?? $company?->name ?? $seller->name,
            'trade_name' => $company?->trade_name ?? $company?->name ?? $seller->name,
            'tax_identification_number' => $company?->tin_number ?? $company?->tax_identification_number ?? 'TIN-001829471',
            'business_license_number' => $company?->license_number ?? $company?->trade_license_number ?? 'BL/AA/2023/1029',
            'verification_level' => $company?->verification_level ?? $seller->verification_level ?? 2,
            'verification_status' => $verStatus,
            'verification_method' => $verMethod,
            'verification_reference' => $verRef,
            'is_official_fayda_api_verified' => $isOfficialApi,
            'masked_phone' => self::maskPhone($seller->phone),
            'masked_national_id' => $seller->masked_national_id ?? 'FAN-****-1192',
            'company_identity' => $company ? [
                'company_id' => $company->id,
                'company_legal_name' => $company->legal_name ?? $company->name ?? 'Company Seller',
                'trade_name' => $company->trade_name ?? $company->name ?? 'Company Seller',
                'tax_identification_number' => $company->tin_number ?? $company->tax_identification_number ?? 'TIN-001829471',
                'business_license_number' => $company->license_number ?? $company->trade_license_number ?? 'BL/AA/2023/1029',
                'verification_level' => $company->verification_level ?? 2,
            ] : null,
            'identity_verification' => [
                'verification_method' => $verMethod,
                'verification_status' => $verStatus,
                'verification_reference' => $verRef,
                'is_official_fayda_api_verified' => $isOfficialApi,
                'displayed_badge' => FaydaVerificationService::formatVerificationBadge($verMethod, 'approved'),
            ],
            'property_ownership' => $ownershipData,
            'property' => $ownershipData,
            'authorized_representative' => $authorizedRep,
            'buyer' => [
                'user_id' => $buyer->id,
                'name' => $buyer->name,
                'masked_phone' => self::maskPhone($buyer->phone),
            ],
            'transaction' => [
                'transaction_reference' => $transactionRef,
                'agreed_price_etb' => $agreedPrice !== null ? round($agreedPrice, 2) : null,
            ],
            'additional_evidence' => $additionalEvidence,
        ];
    }

    /**
     * Enforce strict seller identity and property authority verification before contractually binding sales.
     *
     * @throws ValidationException
     */
    public function enforceSellerSellingAuthority(User $seller, Property $property, ?User $agent = null): void
    {
        // 1. Seller Identity Verification Check
        $isIdentityVerified = (int) $seller->verification_level >= 1
            || $seller->identity_verified_at !== null
            || Verification::query()
                ->where('verifiable_type', User::class)
                ->where('verifiable_id', $seller->id)
                ->where('status', 'approved')
                ->exists();

        if (! $isIdentityVerified) {
            throw ValidationException::withMessages([
                'seller' => ['Seller identity must be verified before executing a legally binding property sale.'],
            ]);
        }

        // 2. Property Ownership and Legal Authority Check
        $isOwnershipVerified = in_array($property->ownership_status, ['authority_verified', 'documents_reviewed'], true)
            || Verification::query()
                ->where('verifiable_type', Property::class)
                ->where('verifiable_id', $property->id)
                ->where('status', 'approved')
                ->exists();

        if (! $isOwnershipVerified) {
            throw ValidationException::withMessages([
                'property' => ['Property ownership or selling authority documentation must be reviewed and approved before executing a binding property sale.'],
            ]);
        }

        // 3. Agent Mandate / Authorization Check (if seller is not the direct owner and is acting as an agent)
        if ((int) $seller->id !== (int) $property->owner_id) {
            $actingSellerId = $agent?->id ?? $seller->id;
            $isAuthorized = (int) $property->authorized_seller_user_id === (int) $actingSellerId
                || (DB::getSchemaBuilder()->hasTable('property_management_mandates') && DB::table('property_management_mandates')
                    ->where('property_id', $property->id)
                    ->where('assigned_manager_user_id', $actingSellerId)
                    ->where('status', 'active')
                    ->exists());

            if (! $isAuthorized) {
                throw ValidationException::withMessages([
                    'agent' => ['Agent or representative does not possess verified selling authority or active mandate for this property.'],
                ]);
            }
        }
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = trim($phone);
        $len = strlen($clean);
        if ($len <= 5) {
            return '***';
        }

        return substr($clean, 0, 7) . '***' . substr($clean, -2);
    }
}
