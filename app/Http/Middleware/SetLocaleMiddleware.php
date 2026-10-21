<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocaleMiddleware
{
    public const array SUPPORTED_LOCALES = ['en', 'am', 'om'];

    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->query('lang')
            ?? $request->session()->get('locale')
            ?? $request->user()?->preferred_locale
            ?? 'en';

        $locale = in_array($requestedLocale, self::SUPPORTED_LOCALES, true)
            ? (string) $requestedLocale
            : 'en';

        if ($request->has('lang') && in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)) {
            $request->session()->put('locale', $locale);
        }

        App::setLocale($locale);

        return $next($request);
    }
}
