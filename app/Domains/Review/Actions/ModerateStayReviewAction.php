<?php

declare(strict_types=1);

namespace App\Domains\Review\Actions;

use App\Domains\Review\Models\ReviewModerationLog;
use App\Domains\Review\Models\StayReview;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ModerateStayReviewAction
{
    /**
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function execute(
        StayReview $review,
        User $staffUser,
        string $action, // 'hide', 'investigate', 'restore', 'remove'
        string $reason
    ): StayReview {
        if (! $staffUser->isStaff()) {
            throw new AuthorizationException('Only authorized staff may moderate reviews.');
        }

        $validActions = ['hide', 'investigate', 'restore', 'remove'];
        if (! in_array($action, $validActions, true)) {
            throw new InvalidArgumentException("Invalid moderation action '{$action}'.");
        }

        return DB::transaction(function () use ($review, $staffUser, $action, $reason): StayReview {
            /** @var StayReview $locked */
            $locked = StayReview::query()->lockForUpdate()->findOrFail($review->id);

            $status = match ($action) {
                'hide' => 'hidden',
                'investigate' => 'under_investigation',
                'restore' => 'approved',
                'remove' => 'removed',
            };

            $isPublished = ($action === 'restore');

            $locked->update([
                'moderation_status' => $status,
                'is_published' => $isPublished,
                'moderation_flag_reason' => $reason,
            ]);

            ReviewModerationLog::query()->create([
                'stay_review_id' => $locked->id,
                'admin_user_id' => $staffUser->id,
                'action' => $action,
                'reason' => $reason,
            ]);

            if ($locked->direction === 'guest_to_host') {
                app(SubmitStayReviewAction::class)->recalculateReputation($locked->property_id, $locked->host_id);
            }

            return $locked;
        });
    }
}
