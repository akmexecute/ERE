<?php

declare(strict_types=1);

namespace App\Domains\Inquiry\Actions;

use App\Domains\Inquiry\Enums\InquiryStatus;
use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Inquiry\Models\Message;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class TransitionInquiryStatusAction
{
    /**
     * Map of permitted status transitions.
     *
     * @var array<string, array<int, string>>
     */
    private const array ALLOWED_TRANSITIONS = [
        'new' => ['contacted', 'viewing', 'lost'],
        'contacted' => ['viewing', 'negotiating', 'lost'],
        'viewing' => ['negotiating', 'offer', 'lost'],
        'negotiating' => ['offer', 'closed', 'lost'],
        'offer' => ['closed', 'lost', 'negotiating'],
        'closed' => ['contacted'], // Reopen
        'lost' => ['contacted'],   // Reopen
    ];

    public function execute(Inquiry $inquiry, string|InquiryStatus $targetStatus, User $actingUser, ?string $notes = null): Inquiry
    {
        // 1. Authorize: Acting user must be seller, buyer, or staff/admin
        $isParticipant = ($actingUser->id === $inquiry->seller_id) || ($actingUser->id === $inquiry->buyer_id);
        $isAdmin = $actingUser->hasAnyRole(['super_admin', 'admin', 'moderator']);

        if (! $isParticipant && ! $isAdmin) {
            throw new AccessDeniedHttpException('You are not authorized to update this inquiry.');
        }

        $targetStr = $targetStatus instanceof InquiryStatus ? $targetStatus->value : $targetStatus;
        $targetEnum = InquiryStatus::tryFrom($targetStr);
        if (! $targetEnum) {
            throw ValidationException::withMessages(['status' => ["Invalid inquiry pipeline status: {$targetStr}"]]);
        }

        $currentStr = $inquiry->status instanceof InquiryStatus ? $inquiry->status->value : (string) $inquiry->status;

        if ($currentStr === $targetStr) {
            return $inquiry;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentStr] ?? [];
        if (! in_array($targetStr, $allowed, true) && ! $isAdmin) {
            throw ValidationException::withMessages([
                'status' => ["Transition from '{$currentStr}' to '{$targetStr}' is not permitted."],
            ]);
        }

        return DB::transaction(function () use ($inquiry, $currentStr, $targetStr, $targetEnum, $actingUser, $notes) {
            $inquiry->update([
                'status' => $targetEnum,
                'last_message_at' => now(),
            ]);

            // Add system audit message to thread
            $label = ucfirst($targetStr);
            $body = "Pipeline stage changed from {$currentStr} to {$label} by {$actingUser->name}.";
            if ($notes) {
                $body .= " Note: {$notes}";
            }

            Message::query()->create([
                'uuid' => (string) Str::uuid(),
                'inquiry_id' => $inquiry->id,
                'sender_id' => $actingUser->id,
                'message_type' => 'system_status_change',
                'body' => $body,
                'created_at' => now(),
            ]);

            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $actingUser->id,
                    'action' => 'inquiry.status_transitioned',
                    'auditable_type' => Inquiry::class,
                    'auditable_id' => (string) $inquiry->id,
                    'old_values' => json_encode(['status' => $currentStr]),
                    'new_values' => json_encode(['status' => $targetStr]),
                    'reason' => $notes ?? 'Pipeline update',
                    'created_at' => now(),
                ]);
            }

            return $inquiry->fresh();
        });
    }
}
