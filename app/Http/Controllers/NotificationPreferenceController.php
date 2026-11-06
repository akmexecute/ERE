<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Notification\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationPreferenceController extends Controller
{
    public function show(Request $request): View|JsonResponse
    {
        $prefs = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'channel_in_app' => true,
                'channel_email' => true,
                'channel_sms' => true,
                'channel_push' => true,
                'channel_telegram' => false,
                'marketing_opt_in' => false,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json($prefs);
        }

        return view('notifications.preferences', ['preferences' => $prefs]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'channel_in_app' => ['nullable', 'boolean'],
            'channel_email' => ['nullable', 'boolean'],
            'channel_sms' => ['nullable', 'boolean'],
            'channel_push' => ['nullable', 'boolean'],
            'channel_telegram' => ['nullable', 'boolean'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ]);

        $prefs = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'channel_in_app' => $validated['channel_in_app'] ?? true,
                'channel_email' => $validated['channel_email'] ?? true,
                'channel_sms' => $validated['channel_sms'] ?? false,
                'channel_push' => $validated['channel_push'] ?? true,
                'channel_telegram' => $validated['channel_telegram'] ?? false,
                'marketing_opt_in' => $validated['marketing_opt_in'] ?? false,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Notification preferences updated successfully.',
                'preferences' => $prefs,
            ]);
        }

        return back()->with('success', 'Notification preferences saved.');
    }
}
