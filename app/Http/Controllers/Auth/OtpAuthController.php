<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Actions\SendOtpAction;
use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class OtpAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.authenticate');
    }

    public function sendOtp(Request $request, SendOtpAction $sendOtp): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'purpose' => ['nullable', 'string', 'max:32'],
        ]);

        $plainCode = $sendOtp->execute(
            rawPhone: (string) $request->input('phone'),
            purpose: (string) ($request->input('purpose') ?? 'login'),
            ipAddress: $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent successfully.',
            // Return demo code in local/debug environment for testing ease
            'debug_code' => config('app.debug') ? $plainCode : null,
        ]);
    }

    public function verifyOtp(Request $request, VerifyOtpAndAuthenticateAction $verifyOtp): JsonResponse|RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'code' => ['required', 'string', 'size:6'],
            'role' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:140'],
        ]);

        $user = $verifyOtp->execute(
            rawPhone: (string) $request->input('phone'),
            code: (string) $request->input('code'),
            name: $request->input('name'),
            initialRole: (string) ($request->input('role') ?? 'buyer'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => url('/dashboard'),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'verification_level' => $user->verification_level,
                ],
            ]);
        }

        return redirect()->intended(url('/dashboard'))->with('status', 'Successfully authenticated.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Logged out successfully.');
    }
}
