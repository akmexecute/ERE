<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Services\IdentityGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdentityVerified
{
    public function __construct(
        private readonly IdentityGateService $gateService
    ) {}

    /**
     * Handle an incoming request and enforce identity/phone qualification for protected actions.
     */
    public function handle(Request $request, Closure $next, string $action = 'general_transaction'): Response
    {
        $user = $request->user();

        // If unauthenticated, pass through to let standard auth middleware handle or redirect
        if (! $user) {
            return $next($request);
        }

        $check = $this->gateService->check($user, $action);

        if (! $check['allowed']) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Identity verification required.',
                    'message' => 'This operation requires National ID/Fayda and a verified telephone number.',
                    'missing' => $check['missing'],
                    'redirect' => route('identity.verify', ['action' => $action]),
                ], 403);
            }

            return redirect()->route('identity.verify', [
                'action' => $action,
                'redirect' => $request->fullUrl(),
            ])->with('error', 'Please complete National ID / Fayda verification to proceed with this transaction.');
        }

        return $next($request);
    }
}

