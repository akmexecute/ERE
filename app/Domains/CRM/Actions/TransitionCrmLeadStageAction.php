<?php

declare(strict_types=1);

namespace App\Domains\CRM\Actions;

use App\Domains\CRM\Enums\CrmPipelineStage;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\CRM\Models\CrmLeadActivity;
use App\Domains\CRM\Models\CrmStageHistory;
use App\Domains\CRM\Services\CrmLeadAccessGuard;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class TransitionCrmLeadStageAction
{
    public function __construct(
        private CrmLeadAccessGuard $guard
    ) {}

    /**
     * Atomically transition a CRM lead to a new stage, recording history and activity.
     */
    public function execute(
        User $actor,
        CrmLead $lead,
        CrmPipelineStage|string $targetStage,
        ?string $notes = null
    ): CrmLead {
        // 1. Authorization check
        if (! $this->guard->canAccessLead($actor, $lead)) {
            throw new AuthorizationException('Unauthorized to modify this CRM lead.');
        }

        $nextStage = $targetStage instanceof CrmPipelineStage
            ? $targetStage
            : CrmPipelineStage::from($targetStage);

        $currentStage = CrmPipelineStage::tryFrom($lead->stage ?? 'new_lead') ?? CrmPipelineStage::NewLead;

        // 2. Validate transition rules
        if (! $currentStage->canTransitionTo($nextStage)) {
            throw new InvalidArgumentException(
                "Invalid CRM stage transition from '{$currentStage->value}' to '{$nextStage->value}'."
            );
        }

        return DB::transaction(function () use ($actor, $lead, $currentStage, $nextStage, $notes): CrmLead {
            $now = CarbonImmutable::now();

            // Calculate duration in previous stage
            $lastHistory = CrmStageHistory::query()
                ->where('crm_lead_id', $lead->id)
                ->latest('transitioned_at')
                ->first();

            $previousTimestamp = $lastHistory?->transitioned_at ?? $lead->created_at ?? $now;
            $durationSeconds = max(0, $now->diffInSeconds($previousTimestamp));

            // Record stage history
            CrmStageHistory::query()->create([
                'crm_lead_id' => $lead->id,
                'actor_user_id' => $actor->id,
                'from_stage' => $currentStage->value,
                'to_stage' => $nextStage->value,
                'duration_in_stage_seconds' => $durationSeconds,
                'notes' => $notes,
                'transitioned_at' => $now,
            ]);

            // Record activity log
            CrmLeadActivity::query()->create([
                'crm_lead_id' => $lead->id,
                'actor_user_id' => $actor->id,
                'activity_type' => 'stage_changed',
                'from_stage' => $currentStage->value,
                'to_stage' => $nextStage->value,
                'summary' => $notes ?: "Stage changed from {$currentStage->label()} to {$nextStage->label()}.",
                'created_at' => $now,
            ]);

            // Update lead
            $updateData = [
                'stage' => $nextStage->value,
                'pipeline_stage' => $nextStage->value,
            ];

            if ($nextStage === CrmPipelineStage::Contacted) {
                $updateData['last_contacted_at'] = $now;
            }

            $lead->update($updateData);

            return $lead->refresh();
        });
    }
}

