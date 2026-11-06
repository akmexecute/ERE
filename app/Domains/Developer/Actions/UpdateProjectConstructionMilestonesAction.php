<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Models\ConstructionMilestone;
use App\Domains\Developer\Models\ConstructionProgressLog;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateProjectConstructionMilestonesAction
{
    public function __construct(
        private CalculateProjectConstructionProgressAction $calculator = new CalculateProjectConstructionProgressAction()
    ) {
    }

    /**
     * Configures custom milestones, updates progress, and recalculates authoritative project completion.
     *
     * @param  int  $projectId
     * @param  User  $actor
     * @param  array<int, array{
     *   stage_code: string,
     *   name_en: string,
     *   name_am?: string|null,
     *   order_index?: int,
     *   progress_weight: float|int,
     *   completion_percentage: float|int,
     *   is_verified?: bool,
     *   evidence_notes?: string|null
     * }>  $milestonesData
     * @param  string|null  $notes
     * @return DevelopmentProject
     */
    public function execute(
        int $projectId,
        User $actor,
        array $milestonesData,
        ?string $notes = null
    ): DevelopmentProject {
        return DB::transaction(function () use ($projectId, $actor, $milestonesData, $notes): DevelopmentProject {
            /** @var DevelopmentProject $project */
            $project = DevelopmentProject::with(['developer.company', 'milestones'])
                ->where('id', $projectId)
                ->lockForUpdate()
                ->firstOrFail();

            // Authorization check: must be company owner, developer user, or super_admin
            $isSuperAdmin = method_exists($actor, 'hasRole') && $actor->hasRole('super_admin');
            $isOwner = (int) $project->developer->company->owner_user_id === (int) $actor->id;
            $isDeveloperUser = (int) $project->developer->user_id === (int) $actor->id;

            if (! $isSuperAdmin && ! $isOwner && ! $isDeveloperUser) {
                throw new DomainException("Unauthorized: You do not have permission to configure construction milestones for this project.");
            }

            // 1. Calculate overall progress and validate weights
            $calcResult = $this->calculator->execute($milestonesData);

            // 2. Persist / update milestones and record logs for changed completion percentages
            $existingMilestones = $project->milestones->keyBy('stage_code');
            $updatedStageCodes = [];

            foreach ($milestonesData as $index => $data) {
                $stageCode = $data['stage_code'];
                $updatedStageCodes[] = $stageCode;
                $newPct = (float) $data['completion_percentage'];
                $weight = (float) $data['progress_weight'];
                $orderIndex = (int) ($data['order_index'] ?? ($index + 1));

                /** @var ConstructionMilestone|null $existing */
                $existing = $existingMilestones->get($stageCode);
                $prevPct = $existing ? (float) $existing->completion_percentage : 0.00;

                // Verification status can only be marked by super_admin / civil engineering verifier
                $isVerified = $existing ? $existing->is_verified : false;
                $verifiedAt = $existing ? $existing->verified_at : null;
                $verifiedBy = $existing ? $existing->verified_by_user_id : null;

                if (isset($data['is_verified']) && $isSuperAdmin) {
                    $isVerified = (bool) $data['is_verified'];
                    if ($isVerified && ! $existing?->is_verified) {
                        $verifiedAt = now();
                        $verifiedBy = $actor->id;
                    }
                }

                $milestone = ConstructionMilestone::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'stage_code' => $stageCode,
                    ],
                    [
                        'name_en' => $data['name_en'],
                        'name_am' => $data['name_am'] ?? null,
                        'order_index' => $orderIndex,
                        'progress_weight' => $weight,
                        'completion_percentage' => $newPct,
                        'is_verified' => $isVerified,
                        'verified_at' => $verifiedAt,
                        'verified_by_user_id' => $verifiedBy,
                        'evidence_notes' => $data['evidence_notes'] ?? ($existing?->evidence_notes),
                    ]
                );

                // If completion percentage changed, record audit log
                if (abs($prevPct - $newPct) > 0.001) {
                    ConstructionProgressLog::create([
                        'milestone_id' => $milestone->id,
                        'project_id' => $project->id,
                        'updated_by_user_id' => $actor->id,
                        'previous_pct' => $prevPct,
                        'new_pct' => $newPct,
                        'notes' => $notes ?? "Progress updated from {$prevPct}% to {$newPct}%.",
                        'created_at' => now(),
                    ]);
                }
            }

            // 3. Update overall progress on project
            $project->update([
                'overall_progress_pct' => $calcResult['overall_progress_pct'],
            ]);

            return $project->load(['milestones.logs']);
        });
    }
}

