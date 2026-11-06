<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     * Enforces immediate termination of active sessions for suspended or banned accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account has been suspended or deactivated. Access is denied.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'phone' => 'Your account has been suspended or deactivated. Access is denied.',
            ]);
        }

        return $next($request);
    }
}
