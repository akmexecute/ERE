<?php

declare(strict_types=1);

namespace App\Domains\Property\Actions;

use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyStatusHistory;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RecordPropertyStatusHistoryAction
{
    /**
     * Atomically transitions property status and logs an auditable history record (SRS Sections 211 & 213).
     * Strictly avoids recording duplicate history entries when the status does not change.
     */
    public function execute(
        Property $property,
        PropertyStatus $newStatus,
        ?User $actor = null,
        ?string $reason = null
    ): Property {
        if ($property->status === $newStatus) {
            return $property;
        }

        return DB::transaction(function () use ($property, $newStatus, $actor, $reason): Property {
            $property->status_change_reason = $reason ? mb_substr($reason, 0, 300) : null;
            $property->status_changed_by_user_id = $actor?->id;

            $property->update([
                'status' => $newStatus,
            ]);

            return $property->refresh();
        });
    }
}
