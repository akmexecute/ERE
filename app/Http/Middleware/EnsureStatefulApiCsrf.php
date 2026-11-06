<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class EnsureStatefulApiCsrf
{
    /**
     * Enforce CSRF protection for state-changing requests originating from a session-authenticated browser context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Safe read-only HTTP methods do not require CSRF tokens
        if (in_array($request->method(), ['HEAD', 'GET', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Automated unit and feature testing suites bypass CSRF unless explicitly testing CSRF
        if (app()->runningUnitTests() && ! $request->header('X-Enforce-CSRF') && ! $request->attributes->get('enforce_csrf')) {
            return $next($request);
        }

        // If the request carries an active session and has an authenticated session user,
        // it MUST provide a valid, verifiable CSRF token matching the session token.
        if ($request->hasSession() && $request->user()) {
            $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

            if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
                try {
                    $token = decrypt($header, false);
                } catch (\Throwable) {
                    $token = null;
                }
            }

            if (! is_string($token) || ! hash_equals($request->session()->token(), $token)) {
                throw new TokenMismatchException('CSRF token mismatch on stateful session API request.');
            }
        }

        return $next($request);
    }
}

