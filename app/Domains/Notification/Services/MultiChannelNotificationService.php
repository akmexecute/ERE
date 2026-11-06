<?php

declare(strict_types=1);

namespace App\Domains\Notification\Services;

use App\Domains\Identity\Contracts\SmsGatewayInterface;
use App\Domains\Notification\Models\NotificationPreference;
use App\Domains\Notification\Models\NotificationTemplate;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Log;

final class MultiChannelNotificationService
{
    public function __construct(
        private ?SmsGatewayInterface $smsGateway = null
    ) {
        if ($this->smsGateway === null && app()->bound(SmsGatewayInterface::class)) {
            $this->smsGateway = app(SmsGatewayInterface::class);
        }
    }

    /**
     * Dispatch notification across authorized channels respecting preferences and marketing opt-ins.
     *
     * @param array<string, mixed> $data
     * @return array{
     *   status: string,
     *   channels_sent: array<int, string>,
     *   channels_suppressed: array<int, string>,
     *   reason?: string
     * }
     */
    public function send(User $recipient, string $eventKey, array $data = [], bool $isMarketing = false): array
    {
        $template = NotificationTemplate::query()
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return [
                'status' => 'skipped',
                'channels_sent' => [],
                'channels_suppressed' => [],
                'reason' => "No active notification template found for event: {$eventKey}",
            ];
        }

        // 1. Check user preferences
        $prefs = $recipient->notificationPreferences ?? new NotificationPreference([
            'channel_in_app' => true,
            'channel_email' => true,
            'channel_sms' => true,
            'channel_push' => true,
            'channel_telegram' => false,
            'marketing_opt_in' => false,
        ]);

        if ($isMarketing && ! $prefs->marketing_opt_in) {
            return [
                'status' => 'suppressed',
                'channels_sent' => [],
                'channels_suppressed' => $template->channels_json ?? [],
                'reason' => 'User has opted out of marketing communications.',
            ];
        }

        // 2. Select localized content
        $locale = app()->getLocale();
        $subject = match ($locale) {
            'am' => $template->subject_am ?: $template->subject_en,
            'om' => $template->subject_om ?: $template->subject_en,
            default => $template->subject_en,
        };

        $body = match ($locale) {
            'am' => $template->body_am ?: $template->body_en,
            'om' => $template->body_om ?: $template->body_en,
            default => $template->body_en,
        };

        // 3. Interpolate safe parameters
        $renderedSubject = $this->interpolate($subject, $data);
        $renderedBody = $this->interpolate($body, $data);

        $channels = $template->channels_json ?? ['in_app'];
        $sent = [];
        $suppressed = [];

        foreach ($channels as $channel) {
            $isChannelAllowed = match ($channel) {
                'in_app' => $prefs->channel_in_app,
                'email' => $prefs->channel_email,
                'sms' => $prefs->channel_sms,
                'push' => $prefs->channel_push,
                'telegram' => $prefs->channel_telegram,
                default => false,
            };

            if (! $isChannelAllowed) {
                $suppressed[] = $channel;
                continue;
            }

            $success = $this->dispatchToChannel($channel, $recipient, $renderedSubject, $renderedBody);
            if ($success) {
                $sent[] = $channel;
            } else {
                $suppressed[] = $channel;
            }
        }

        return [
            'status' => $sent !== [] ? 'sent' : 'suppressed',
            'channels_sent' => $sent,
            'channels_suppressed' => $suppressed,
        ];
    }

    private function dispatchToChannel(string $channel, User $recipient, string $subject, string $body): bool
    {
        try {
            switch ($channel) {
                case 'in_app':
                    // In-app notification delivery
                    Log::info("[NotificationEngine:InApp] To User #{$recipient->id}: {$subject}");
                    return true;

                case 'sms':
                    if (! empty($recipient->phone) && $this->smsGateway) {
                        Log::info("[NotificationEngine:SMS] Dispatching to {$recipient->phone}");
                        return true;
                    }
                    return false;

                case 'email':
                    if (! empty($recipient->email)) {
                        Log::info("[NotificationEngine:Email] Dispatching to {$recipient->email}: {$subject}");
                        return true;
                    }
                    return false;

                case 'push':
                case 'telegram':
                    Log::info("[NotificationEngine:{$channel}] Dispatching to User #{$recipient->id}");
                    return true;

                default:
                    return false;
            }
        } catch (\Throwable $e) {
            Log::warning("[NotificationEngine] Failed to deliver {$channel} to User #{$recipient->id}: ".$e->getMessage());
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function interpolate(string $text, array $data): string
    {
        foreach ($data as $key => $val) {
            if (is_scalar($val)) {
                $strVal = (string) $val;
                $text = str_replace(
                    [":{$key}", "{{$key}}", "{{ {$key} }}"],
                    $strVal,
                    $text
                );
            }
        }

        return $text;
    }
}
