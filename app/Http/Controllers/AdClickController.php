<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Advertising\Models\AdCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdClickController extends Controller
{
    /**
     * Record ad click and redirect user to target destination.
     */
    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $campaign = AdCampaign::query()->where('uuid', $uuid)->firstOrFail();

        $campaign->recordClick();

        $targetUrl = $campaign->target_url;
        if (! $targetUrl || ! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return redirect('/');
        }

        return redirect()->away($targetUrl);
    }
}

