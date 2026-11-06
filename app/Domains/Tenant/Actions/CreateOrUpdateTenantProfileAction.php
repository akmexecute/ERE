<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Enums\TenantStatus;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateOrUpdateTenantProfileAction
{
    public function __construct(
        private GenerateTenantCodeAction $codeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $user, array $payload): TenantProfile
    {
        return DB::transaction(function () use ($user, $payload): TenantProfile {
            /** @var TenantProfile|null $profile */
            $profile = TenantProfile::query()->where('user_id', $user->id)->first();

            $income = isset($payload['monthly_income_etb']) && is_numeric($payload['monthly_income_etb'])
                ? round((float) $payload['monthly_income_etb'], 2)
                : null;

            if (! $profile) {
                $code = $this->codeGenerator->execute();

                $profile = TenantProfile::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_code' => $code,
                    'user_id' => $user->id,
                    'occupation' => $payload['occupation'] ?? null,
                    'employer_name' => $payload['employer_name'] ?? null,
                    'monthly_income_etb' => $income,
                    'emergency_contact_name' => $payload['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $payload['emergency_contact_phone'] ?? null,
                    'emergency_contact_relationship' => $payload['emergency_contact_relationship'] ?? null,
                    'identity_document_id' => $payload['identity_document_id'] ?? null,
                    'income_proof_document_id' => $payload['income_proof_document_id'] ?? null,
                    'status' => TenantStatus::Active,
                ]);
            } else {
                $profile->update([
                    'occupation' => $payload['occupation'] ?? $profile->occupation,
                    'employer_name' => $payload['employer_name'] ?? $profile->employer_name,
                    'monthly_income_etb' => $income ?? $profile->monthly_income_etb,
                    'emergency_contact_name' => $payload['emergency_contact_name'] ?? $profile->emergency_contact_name,
                    'emergency_contact_phone' => $payload['emergency_contact_phone'] ?? $profile->emergency_contact_phone,
                    'emergency_contact_relationship' => $payload['emergency_contact_relationship'] ?? $profile->emergency_contact_relationship,
                    'identity_document_id' => $payload['identity_document_id'] ?? $profile->identity_document_id,
                    'income_proof_document_id' => $payload['income_proof_document_id'] ?? $profile->income_proof_document_id,
                ]);
            }

            // Assign tenant role if user doesn't already have it
            $tenantRoleId = DB::table('roles')->where('slug', 'tenant')->value('id');
            if ($tenantRoleId) {
                DB::table('user_roles')->updateOrInsert([
                    'user_id' => $user->id,
                    'role_id' => $tenantRoleId,
                ]);
            }

            return $profile->fresh();
        });
    }
}
