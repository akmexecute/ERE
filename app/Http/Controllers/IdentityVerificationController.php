<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Identity\Actions\SubmitNationalIdVerificationAction;
use App\Domains\Identity\Services\IdentityGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class IdentityVerificationController extends Controller
{
    public function show(Request $request, IdentityGateService $gateService): View
    {
        $user = $request->user();
        $intendedAction = (string) $request->query('action', 'general_transaction');
        $redirectUrl = (string) $request->query('redirect', route('dashboard'));

        $gateCheck = $gateService->check($user, $intendedAction);

        return view('identity.verify', [
            'user' => $user,
            'gateCheck' => $gateCheck,
            'intendedAction' => $intendedAction,
            'redirectUrl' => $redirectUrl,
        ]);
    }

    public function status(Request $request, IdentityGateService $gateService): JsonResponse
    {
        $user = $request->user();
        $action = (string) $request->query('action', 'general_transaction');
        $check = $gateService->check($user, $action);

        return response()->json([
            'success' => true,
            'data' => $check,
        ]);
    }

    public function store(Request $request, SubmitNationalIdVerificationAction $action): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $rules = [
            'national_id_number' => ['required', 'string', 'min:4', 'max:64'],
            'national_id_type' => ['required', 'string', 'in:fayda,national_id,kebele_id,fayda_digital_qr,passport'],
            'id_front_image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'id_back_image' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ];

        // Back image required unless user explicitly selects digital single-sided QR ID
        if ($request->input('national_id_type') !== 'fayda_digital_qr') {
            $rules['id_back_image'] = ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'];
        }

        $validated = $request->validate($rules);

        $action->execute(
            user: $user,
            nationalIdNumber: (string) $validated['national_id_number'],
            nationalIdType: (string) $validated['national_id_type'],
            frontImage: $request->file('id_front_image'),
            backImage: $request->file('id_back_image')
        );

        $redirect = $request->input('redirect_url', route('dashboard'));

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'National ID and documentation submitted for verification successfully.',
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('success', 'Your identification documents have been securely uploaded and submitted for verification.');
    }
}

