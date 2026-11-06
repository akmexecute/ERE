<?php

declare(strict_types=1);

namespace App\Domains\Cleaning\Actions;

use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionCleaningTaskAction
{
    /**
     * @param array<string, mixed>|null $checklist
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        CleaningTask $task,
        User $user,
        CleaningTaskStatus $targetStatus,
        ?int $assignCleanerId = null,
        ?array $checklist = null,
        ?string $inspectionNotes = null,
        bool $isInspectionRejection = false
    ): CleaningTask {
        $current = $task->status;

        // 1. Validate State Machine Transition
        if ($isInspectionRejection) {
            if ($current !== CleaningTaskStatus::Inspection) {
                throw ValidationException::withMessages([
                    'status' => ['Can only reject cleaning work when task is in inspection stage.'],
                ]);
            }
            $targetStatus = CleaningTaskStatus::Cleaning;
        } elseif (! $current->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid housekeeping transition from '{$current->value}' to '{$targetStatus->value}'."],
            ]);
        }

        // 2. Authorize based on transition
        $isHost = (int) $task->host?->user_id === (int) $user->id || (int) $task->property?->owner_id === (int) $user->id;
        $isAssignedCleaner = (int) $task->cleaner?->host_id === (int) $task->host_id;
        $isStaff = $user->isStaff();

        if ($targetStatus === CleaningTaskStatus::Ready) {
            // CRITICAL: Cleaners cannot approve their own work as ready where host approval is required!
            if (! $isHost && ! $isStaff) {
                throw new AuthorizationException('Only an authorized host, property manager, or inspector can approve a cleaning task as ready.');
            }
        }

        return DB::transaction(function () use (
            $task,
            $user,
            $targetStatus,
            $assignCleanerId,
            $checklist,
            $inspectionNotes,
            $isInspectionRejection
        ): CleaningTask {
            /** @var CleaningTask $lockedTask */
            $lockedTask = CleaningTask::query()->lockForUpdate()->findOrFail($task->id);

            $now = CarbonImmutable::now();
            $updates = [
                'status' => $targetStatus,
            ];

            if ($assignCleanerId !== null) {
                $updates['cleaner_id'] = $assignCleanerId;
            }

            if ($checklist !== null) {
                $updates['checklist_json'] = $checklist;
            }

            if ($inspectionNotes !== null) {
                $updates['inspection_notes'] = $inspectionNotes;
            }

            if ($targetStatus === CleaningTaskStatus::Cleaning && ! $lockedTask->started_at) {
                $updates['started_at'] = $now;
            }

            if ($targetStatus === CleaningTaskStatus::Inspection) {
                // Cleaner submitted for inspection
                $updates['inspected_at'] = null;
            }

            if ($targetStatus === CleaningTaskStatus::Ready) {
                $updates['inspected_by_user_id'] = $user->id;
                $updates['inspected_at'] = $now;
                $updates['ready_at'] = $now;

                // Mark accommodation unit ready
                if ($lockedTask->accommodationUnit) {
                    $lockedTask->accommodationUnit->update(['status' => 'ready']);
                }
            } elseif ($targetStatus === CleaningTaskStatus::Cleaning && $isInspectionRejection) {
                // Re-cleaning
                $updates['ready_at'] = null;
                if ($lockedTask->accommodationUnit) {
                    $lockedTask->accommodationUnit->update(['status' => 'cleaning']);
                }
            }

            $lockedTask->update($updates);

            return $lockedTask->fresh(['cleaner', 'inspector', 'accommodationUnit']);
        });
    }
}

