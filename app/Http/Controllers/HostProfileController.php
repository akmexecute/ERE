<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Host\Models\Host;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class HostProfileController extends Controller
{
    public const array VALID_HOST_TYPES = [
        'individual_owner',
        'company',
        'property_manager',
        'hotel',
        'guesthouse',
        'developer',
        'professional_host',
    ];

    public const array VALID_PAYOUT_SCHEDULES = [
        'immediately',
        'after_checkin',
        'after_checkout',
        'weekly',
        'monthly',
    ];

    /**
     * Show host profile.
     */
    public function show(Request $request, ?string $uuid = null): View|JsonResponse
    {
        $user = $request->user();

        $host = $uuid
            ? Host::query()->where('uuid', $uuid)->with(['user', 'stayProfiles.listing'])->firstOrFail()
            : Host::query()->where('user_id', $user?->id)->with(['user', 'stayProfiles.listing'])->first();

        if (! $host) {
            abort(404, 'Host profile not found.');
        }

        if ($request->wantsJson()) {
            return response()->json($host);
        }

        return view('host.profile', compact('host'));
    }

    /**
     * Register a new host profile for authenticated user (1 host per user).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        // Enforce 1-host-profile-per-user
        if (Host::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'host' => ['You already have an active host profile.'],
            ]);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:140'],
            'host_type' => ['required', 'string', Rule::in(self::VALID_HOST_TYPES)],
            'payout_schedule' => ['nullable', 'string', Rule::in(self::VALID_PAYOUT_SCHEDULES)],
            'bio_en' => ['nullable', 'string', 'max:1000'],
            'bio_am' => ['nullable', 'string', 'max:1000'],
            'bio_om' => ['nullable', 'string', 'max:1000'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'company_id' => $validated['company_id'] ?? null,
            'host_type' => $validated['host_type'],
            'display_name' => $validated['display_name'],
            'bio_en' => $validated['bio_en'] ?? null,
            'bio_am' => $validated['bio_am'] ?? null,
            'bio_om' => $validated['bio_om'] ?? null,
            'payout_schedule' => $validated['payout_schedule'] ?? 'after_checkin',
            'status' => 'active',
        ]);

        $user->assignRoleBySlug('host');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Host profile created successfully.',
                'host' => $host,
            ], 201);
        }

        return redirect()->route('dashboard', ['tab' => 'stays'])->with('success', 'Host profile registered successfully.');
    }

    /**
     * Update existing host profile, strictly enforcing ownership.
     */
    public function update(Request $request, string $uuid): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $host = Host::query()->where('uuid', $uuid)->firstOrFail();

        // Enforce ownership: only the host user or staff can edit
        $isStaff = $user->hasAnyRole(['super_admin', 'platform_admin']);
        if ($host->user_id !== $user->id && ! $isStaff) {
            throw new AccessDeniedHttpException('You are not authorized to modify this host profile.');
        }

        $validated = $request->validate([
            'display_name' => ['sometimes', 'required', 'string', 'max:140'],
            'host_type' => ['sometimes', 'required', 'string', Rule::in(self::VALID_HOST_TYPES)],
            'payout_schedule' => ['sometimes', 'required', 'string', Rule::in(self::VALID_PAYOUT_SCHEDULES)],
            'bio_en' => ['nullable', 'string', 'max:1000'],
            'bio_am' => ['nullable', 'string', 'max:1000'],
            'bio_om' => ['nullable', 'string', 'max:1000'],
        ]);

        $host->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Host profile updated successfully.',
                'host' => $host->fresh(),
            ]);
        }

        return back()->with('success', 'Host profile updated.');
    }
}
