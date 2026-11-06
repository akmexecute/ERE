<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Agency\Actions\VerifyAgentProfileAction;
use App\Domains\Agency\Models\AgentProfile;
use App\Domains\Agency\Services\AgentPerformanceMetricsCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentProfileController extends Controller
{
    public function __construct(
        protected AgentPerformanceMetricsCalculator $metricsCalculator,
        protected VerifyAgentProfileAction $verifyAction
    ) {}

    /**
     * Display public agent profile.
     */
    public function show(string $code): View
    {
        $profile = AgentProfile::query()
            ->where('agent_code', $code)
            ->orWhere('id', is_numeric($code) ? (int) $code : 0)
            ->with(['user', 'company', 'branch', 'serviceLocations', 'listings.property'])
            ->firstOrFail();

        // Ensure metrics are up to date
        $this->metricsCalculator->recalculate($profile);

        return view('agents.show', [
            'agent' => $profile,
            'user' => $profile->user,
            'company' => $profile->company,
            'branch' => $profile->branch,
            'locations' => $profile->serviceLocations,
            'listings' => $profile->listings()->where('status', 'published')->latest()->take(6)->get(),
        ]);
    }

    /**
     * Verify an agent profile (restricted to authorized platform staff).
     */
    public function verify(Request $request, string $code): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $profile = AgentProfile::query()
            ->where('agent_code', $code)
            ->firstOrFail();

        $this->verifyAction->execute(
            actor: $user,
            profile: $profile,
            notes: $request->input('notes')
        );

        return back()->with('success', "Agent {$profile->agent_code} has been successfully verified under Proclamation No. 1357/2024.");
    }
}

