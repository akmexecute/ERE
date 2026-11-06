<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Domains\Developer\Actions\AmendDeveloperSalesContractAction;
use App\Domains\Developer\Actions\ManageDeveloperPaymentAccountAction;
use App\Domains\Developer\Actions\SubmitManualInstallmentPaymentAction;
use App\Domains\Developer\Actions\UpdateProjectConstructionMilestonesAction;
use App\Domains\Developer\Actions\VerifyManualInstallmentPaymentAction;
use App\Domains\Developer\Models\DeveloperPaymentAccount;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DeveloperProjectApiController extends Controller
{
    /**
     * GET /api/v1/projects/{project}/payment-accounts
     * Returns active developer manual payment accounts for a project.
     */
    public function getPaymentAccounts(int $projectId): JsonResponse
    {
        $project = DevelopmentProject::with('developer')->findOrFail($projectId);

        $accounts = DeveloperPaymentAccount::query()
            ->where('is_active', true)
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhere('developer_id', $project->developer_id)
                    ->orWhere('company_id', $project->company_id);
            })
            ->get();

        return response()->json([
            'project_id' => $project->id,
            'accounts' => $accounts,
        ]);
    }

    /**
     * POST /api/v1/developer/payment-accounts
     * Developer registers or updates an official manual bank transfer account.
     */
    public function storePaymentAccount(
        Request $request,
        ManageDeveloperPaymentAccountAction $action
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:developer_payment_accounts,id'],
            'developer_id' => ['required', 'integer', 'exists:developer_profiles,id'],
            'project_id' => ['nullable', 'integer', 'exists:development_projects,id'],
            'account_label' => ['required', 'string', 'max:120'],
            'institution_name' => ['required', 'string', 'max:120'],
            'account_holder_name' => ['required', 'string', 'max:160'],
            'account_number' => ['required', 'string', 'max:64'],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'swift_code' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
            'transfer_instructions' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $account = $action->execute($user, $validated);

            return response()->json([
                'id' => $account->id,
                'uuid' => $account->uuid,
                'account_label' => $account->account_label,
                'institution_name' => $account->institution_name,
                'account_holder_name' => $account->account_holder_name,
                'account_number' => $account->account_number,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
                'message' => 'Developer payment destination account saved successfully.',
            ], 201);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/contracts/{contract}/payments/manual
     * Buyer submits proof of manual bank transfer installment payment.
     */
    public function submitManualPayment(
        Request $request,
        int $contractId,
        SubmitManualInstallmentPaymentAction $action
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'installment_id' => ['required', 'integer', 'exists:contract_installments,id'],
            'payment_account_id' => ['required', 'integer', 'exists:developer_payment_accounts,id'],
            'amount_etb' => ['required', 'numeric', 'min:1'],
            'transfer_reference' => ['required', 'string', 'max:100'],
            'receipt_url' => ['nullable', 'string', 'max:255'],
            'receipt_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $transaction = $action->execute(
                contractId: $contractId,
                installmentId: (int) $validated['installment_id'],
                paymentAccountId: (int) $validated['payment_account_id'],
                amountEtb: (float) $validated['amount_etb'],
                transferReference: $validated['transfer_reference'],
                payer: $user,
                receiptUrl: $validated['receipt_url'] ?? null,
                receiptDocumentId: isset($validated['receipt_document_id']) ? (int) $validated['receipt_document_id'] : null,
                notes: $validated['notes'] ?? null
            );

            return response()->json([
                'id' => $transaction->id,
                'transaction_reference' => $transaction->transaction_reference,
                'amount_etb' => (float) $transaction->amount_etb,
                'transfer_reference' => $transaction->transfer_reference,
                'status' => $transaction->status,
                'message' => 'Manual payment submitted for verification. Installment will update once confirmed.',
            ], 201);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Conflict', 'message' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/payments/{payment}/verify
     * Authorized developer or administrator verifies or rejects a submitted payment claim.
     */
    public function verifyPayment(
        Request $request,
        int $paymentId,
        VerifyManualInstallmentPaymentAction $action
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'decision_notes' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string', 'required_if:approved,false'],
        ]);

        try {
            $transaction = $action->execute(
                transactionId: $paymentId,
                approved: (bool) $validated['approved'],
                verifier: $user,
                decisionNotes: $validated['decision_notes'] ?? null,
                rejectionReason: $validated['rejection_reason'] ?? null
            );

            return response()->json([
                'id' => $transaction->id,
                'transaction_reference' => $transaction->transaction_reference,
                'status' => $transaction->status,
                'verified_at' => $transaction->verified_at?->toIso8601String(),
                'contract_id' => $transaction->contract_id,
                'installment_id' => $transaction->installment_id,
                'message' => $transaction->isVerified()
                    ? 'Payment verified and installment successfully updated.'
                    : 'Payment claim rejected.',
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Unauthorized or Invalid State', 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/projects/{project}/milestones
     * Developer configures custom construction milestones and updates progress.
     */
    public function configureMilestones(
        Request $request,
        int $projectId,
        UpdateProjectConstructionMilestonesAction $action
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'milestones' => ['required', 'array', 'min:1'],
            'milestones.*.stage_code' => ['required', 'string', 'max:32'],
            'milestones.*.name_en' => ['required', 'string', 'max:120'],
            'milestones.*.name_am' => ['nullable', 'string', 'max:120'],
            'milestones.*.order_index' => ['nullable', 'integer'],
            'milestones.*.progress_weight' => ['required', 'numeric', 'min:0'],
            'milestones.*.completion_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'milestones.*.is_verified' => ['nullable', 'boolean'],
            'milestones.*.evidence_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $project = $action->execute(
                projectId: $projectId,
                actor: $user,
                milestonesData: $validated['milestones'],
                notes: $validated['notes'] ?? null
            );

            return response()->json([
                'project_id' => $project->id,
                'overall_progress_pct' => (float) $project->overall_progress_pct,
                'milestones' => $project->milestones,
                'message' => 'Project construction stages and weighted progress updated successfully.',
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Unauthorized', 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/contracts/{contract}/amend
     * Authorized developer officer amends a contract price and regenerates payment schedule.
     */
    public function amendContract(
        Request $request,
        int $contractId,
        AmendDeveloperSalesContractAction $action
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'revised_price_etb' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $contract = $action->execute(
                contractId: $contractId,
                revisedPriceEtb: (float) $validated['revised_price_etb'],
                reason: $validated['reason'],
                authorizedUser: $user
            );

            return response()->json([
                'id' => $contract->id,
                'contract_code' => $contract->contract_code,
                'contract_price_etb' => (float) $contract->contract_price_etb,
                'balance_payable_etb' => (float) $contract->balance_payable_etb,
                'installments' => $contract->installments,
                'amendments' => $contract->amendments,
                'message' => 'Sales contract price amendment proposed and pending buyer consent. Active contract remains unchanged until accepted.',
            ]);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Unauthorized or Invalid State', 'message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'Validation Error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/contracts/{contract}/amendments/{amendment}/accept
     * Contract buyer explicitly accepts a proposed contract price amendment.
     */
    public function acceptAmendment(
        Request $request,
        int $contractId,
        int $amendmentId
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($contractId, $amendmentId, $user): JsonResponse {
            /** @var \App\Domains\Developer\Models\DeveloperSalesContract $contract */
            $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with('installments')
                ->where('id', $contractId)
                ->lockForUpdate()
                ->firstOrFail();

            // Strict RBAC: ONLY the contracted buyer can accept amendments; no admin impersonation
            if ((int) $contract->buyer_user_id !== (int) $user->id) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Only the contracted buyer may accept contract amendments.'], 403);
            }

            /** @var \App\Domains\Developer\Models\ContractAmendment $amendment */
            $amendment = \App\Domains\Developer\Models\ContractAmendment::where('id', $amendmentId)
                ->where('contract_id', $contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent acceptance
            if ($amendment->isAccepted() || $amendment->accepted_by_buyer) {
                return response()->json([
                    'id' => $amendment->id,
                    'amendment_code' => $amendment->amendment_code,
                    'status' => 'accepted',
                    'accepted_by_buyer' => true,
                    'accepted_at' => $amendment->accepted_at?->toIso8601String(),
                    'contract_price_etb' => (float) $contract->contract_price_etb,
                    'message' => 'Contract price amendment already accepted.',
                ]);
            }

            if (! $amendment->isPending()) {
                return response()->json([
                    'error' => 'Invalid State',
                    'message' => "Cannot accept an amendment with status '{$amendment->status}'.",
                ], 422);
            }

            // ATOMICALLY APPLY REVISED SCHEDULE & PRICE TO THE ACTIVE CONTRACT
            $revisedSchedule = $amendment->revised_schedule_snapshot_json ?? [];
            $totalPaidSoFar = 0.0;
            foreach ($revisedSchedule as $revisedItem) {
                $seq = $revisedItem['sequence_number'];
                /** @var \App\Domains\Developer\Models\ContractInstallment|null $existingInstallment */
                $existingInstallment = $contract->installments->firstWhere('sequence_number', $seq);

                $paidAmount = $existingInstallment ? (float) $existingInstallment->paid_amount_etb : 0.00;
                $newAmount = (float) $revisedItem['amount_etb'];
                $outstanding = max(0.0, round($newAmount - $paidAmount, 2));
                $newStatus = ($outstanding <= 0.0 && $paidAmount > 0) ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending');

                $totalPaidSoFar += $paidAmount;

                if ($existingInstallment) {
                    $existingInstallment->update([
                        'percentage' => $revisedItem['percentage'],
                        'amount_etb' => $newAmount,
                        'outstanding_balance_etb' => $outstanding,
                        'status' => $newStatus,
                    ]);
                } else {
                    \App\Domains\Developer\Models\ContractInstallment::create([
                        'contract_id' => $contract->id,
                        'sequence_number' => $seq,
                        'milestone_label_en' => $revisedItem['milestone_label_en'],
                        'percentage' => $revisedItem['percentage'],
                        'amount_etb' => $newAmount,
                        'paid_amount_etb' => $paidAmount,
                        'outstanding_balance_etb' => $outstanding,
                        'status' => $newStatus,
                    ]);
                }
            }

            $newBalancePayable = max(0.0, round((float) $amendment->revised_price_etb - $totalPaidSoFar, 2));
            $contract->update([
                'contract_price_etb' => $amendment->revised_price_etb,
                'balance_payable_etb' => $newBalancePayable,
            ]);

            $amendment->update([
                'status' => 'accepted',
                'accepted_by_buyer' => true,
                'accepted_at' => now(),
                'decided_by_user_id' => $user->id,
            ]);

            return response()->json([
                'id' => $amendment->id,
                'amendment_code' => $amendment->amendment_code,
                'status' => 'accepted',
                'accepted_by_buyer' => true,
                'accepted_at' => $amendment->accepted_at->toIso8601String(),
                'contract_price_etb' => (float) $contract->contract_price_etb,
                'balance_payable_etb' => (float) $contract->balance_payable_etb,
                'message' => 'Contract price amendment accepted by buyer. Revised installment schedule is now effective.',
            ]);
        });
    }

    /**
     * POST /api/v1/contracts/{contract}/amendments/{amendment}/reject
     * Contract buyer rejects a proposed contract price amendment.
     */
    public function rejectAmendment(
        Request $request,
        int $contractId,
        int $amendmentId
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $contractId, $amendmentId, $user): JsonResponse {
            /** @var \App\Domains\Developer\Models\DeveloperSalesContract $contract */
            $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with('installments')->findOrFail($contractId);

            // Strict RBAC: ONLY the contracted buyer can reject amendments
            if ((int) $contract->buyer_user_id !== (int) $user->id) {
                return response()->json(['error' => 'Unauthorized', 'message' => 'Only the contracted buyer may reject contract amendments.'], 403);
            }

            /** @var \App\Domains\Developer\Models\ContractAmendment $amendment */
            $amendment = \App\Domains\Developer\Models\ContractAmendment::where('id', $amendmentId)
                ->where('contract_id', $contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent rejection
            if ($amendment->isRejected()) {
                return response()->json([
                    'message' => 'Contract price amendment already rejected.',
                    'amendment_id' => $amendment->id,
                    'status' => 'rejected',
                    'contract_price_etb' => (float) $contract->contract_price_etb,
                ]);
            }

            if (! $amendment->isPending()) {
                return response()->json([
                    'error' => 'Invalid State',
                    'message' => "Cannot reject an amendment that is already '{$amendment->status}'.",
                ], 422);
            }

            // CRITICAL REQUIREMENT: NEVER DELETE A REJECTED AMENDMENT!
            // Mark as rejected and preserve all historical snapshots, timestamps, and reasoning.
            $amendment->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by_user_id' => $user->id,
                'decided_by_user_id' => $user->id,
                'rejection_reason' => $request->input('reason', 'Buyer rejected the proposed price revision.'),
            ]);

            // Note: Since the proposal never altered the active contract, the original contract
            // and installment obligations remain intact.

            return response()->json([
                'message' => 'Contract price amendment rejected and preserved in historical records.',
                'amendment_id' => $amendment->id,
                'status' => 'rejected',
                'contract_price_etb' => (float) $contract->contract_price_etb,
            ]);
        });
    }

    /**
     * POST /api/v1/projects/{project}/payment-plans
     * Creates or updates a customized project payment plan.
     */
    public function storePaymentPlan(
        Request $request,
        int $projectId
    ): JsonResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required.'], 401);
        }

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:140'],
            'name_am' => ['nullable', 'string', 'max:140'],
            'is_default' => ['nullable', 'boolean'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.sequence_number' => ['required', 'integer', 'min:1'],
            'installments.*.milestone_label_en' => ['required', 'string', 'max:140'],
            'installments.*.milestone_label_am' => ['nullable', 'string', 'max:140'],
            'installments.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'installments.*.trigger_milestone_code' => ['nullable', 'string', 'max:32'],
            'installments.*.due_days_after_trigger' => ['nullable', 'integer', 'min:0'],
        ]);

        $project = DevelopmentProject::with('developer.company')->findOrFail($projectId);
        $isSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $isOwner = (int) $project->developer->company->owner_user_id === (int) $user->id;
        $isDevUser = (int) $project->developer->user_id === (int) $user->id;

        if (! $isSuperAdmin && ! $isOwner && ! $isDevUser) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Unauthorized to configure payment plans for this project.'], 403);
        }

        // Validate percentage sum = 100.00%
        $totalPct = array_sum(array_column($validated['installments'], 'percentage'));
        if (abs($totalPct - 100.0) > 0.01) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => sprintf("Payment plan installment percentages must total exactly 100.00%%. Provided sum: %.2f%%.", $totalPct),
            ], 422);
        }

        $plan = \Illuminate\Support\Facades\DB::transaction(function () use ($project, $validated): \App\Domains\Developer\Models\DeveloperPaymentPlan {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault) {
                \App\Domains\Developer\Models\DeveloperPaymentPlan::where('project_id', $project->id)
                    ->update(['is_default' => false]);
            }

            $plan = \App\Domains\Developer\Models\DeveloperPaymentPlan::create([
                'project_id' => $project->id,
                'name_en' => $validated['name_en'],
                'name_am' => $validated['name_am'] ?? null,
                'is_default' => $isDefault,
                'is_active' => true,
                'version' => 1,
            ]);

            foreach ($validated['installments'] as $inst) {
                \App\Domains\Developer\Models\PaymentPlanInstallment::create([
                    'payment_plan_id' => $plan->id,
                    'sequence_number' => (int) $inst['sequence_number'],
                    'milestone_label_en' => $inst['milestone_label_en'],
                    'milestone_label_am' => $inst['milestone_label_am'] ?? null,
                    'percentage' => (float) $inst['percentage'],
                    'trigger_milestone_code' => $inst['trigger_milestone_code'] ?? null,
                    'due_days_after_trigger' => (int) ($inst['due_days_after_trigger'] ?? 0),
                ]);
            }

            return $plan->load('installments');
        });

        return response()->json([
            'id' => $plan->id,
            'name_en' => $plan->name_en,
            'is_default' => $plan->is_default,
            'installments' => $plan->installments,
            'message' => 'Payment plan created successfully with 100% milestone schedule.',
        ], 201);
    }
}

