<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Services\AuditService;
use App\Domains\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SecurityAdminController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('security.view')) {
            abort(403, 'Unauthorized access to account security center.');
        }

        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        // Aggregate statistics from login_histories
        $totalAttempts = DB::table('login_histories')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $successfulAttempts = DB::table('login_histories')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('succeeded', true)
            ->count();

        $failedAttempts = DB::table('login_histories')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('succeeded', false)
            ->count();

        $suspiciousAttempts = DB::table('login_histories')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('is_suspicious', true)
            ->count();

        $failureRate = $totalAttempts > 0
            ? (float) number_format(($failedAttempts / $totalAttempts) * 100, 1)
            : 0.0;

        // Query login histories with search and status filters
        $historyQuery = DB::table('login_histories')
            ->leftJoin('users', 'users.id', '=', 'login_histories.user_id')
            ->select([
                'login_histories.*',
                'users.name as user_name',
                'users.status as user_status',
                'users.verification_level as user_verification_level',
            ])
            ->orderByDesc('login_histories.created_at');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $historyQuery->where(function ($q) use ($search): void {
                $q->where('login_histories.phone_or_email', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('login_histories.ip_address', 'like', "%{$search}%");
            });
        }

        $filterStatus = (string) $request->input('status', 'all');
        if ($filterStatus === 'success') {
            $historyQuery->where('login_histories.succeeded', true);
        } elseif ($filterStatus === 'failed') {
            $historyQuery->where('login_histories.succeeded', false);
        } elseif ($filterStatus === 'suspicious') {
            $historyQuery->where('login_histories.is_suspicious', true);
        }

        $timeRange = (string) $request->input('time_range', '30d');
        if ($timeRange === '24h') {
            $historyQuery->where('login_histories.created_at', '>=', now()->subDay());
        } elseif ($timeRange === '7d') {
            $historyQuery->where('login_histories.created_at', '>=', now()->subDays(7));
        }

        $histories = $historyQuery->paginate(20)->withQueryString();

        // Flagged / Suspended users
        $suspendedUsers = User::query()
            ->whereIn('status', ['suspended', 'banned'])
            ->orderByDesc('updated_at')
            ->take(15)
            ->get();

        // Recent administrative security audit logs
        $securityLogs = AuditLog::query()
            ->where(function ($q): void {
                $q->where('action', 'like', '%security%')
                    ->orWhere('action', 'like', '%user.%')
                    ->orWhere('action', 'like', '%auth%');
            })
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return view('admin.security.index', [
            'totalAttempts' => $totalAttempts,
            'successfulAttempts' => $successfulAttempts,
            'failedAttempts' => $failedAttempts,
            'failureRate' => $failureRate,
            'suspiciousAttempts' => $suspiciousAttempts,
            'histories' => $histories,
            'suspendedUsers' => $suspendedUsers,
            'securityLogs' => $securityLogs,
            'currentSearch' => $search,
            'currentStatus' => $filterStatus,
            'currentTimeRange' => $timeRange,
        ]);
    }

    public function toggleUserStatus(Request $request, int $userId): RedirectResponse
    {
        $admin = $request->user();
        if (! $admin || ! $admin->hasPermission('security.manage')) {
            abort(403, 'Unauthorized to alter account security status.');
        }

        $targetUser = User::query()->findOrFail($userId);
        $request->validate([
            'status' => ['required', 'string', 'in:active,suspended,banned'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $newStatus = (string) $request->input('status');
        $oldStatus = $targetUser->status;

        $targetUser->status = $newStatus;
        $targetUser->save();

        $this->auditService->log(
            action: 'user.status_changed',
            auditable: $targetUser,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            reason: (string) $request->input('reason'),
            userId: $admin->id
        );

        return redirect()->back()->with('status', "Account status for {$targetUser->name} updated to '{$newStatus}'.");
    }
}
