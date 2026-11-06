<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Inquiry\Models\Inquiry;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SellerDashboardController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $seller = $request->user();

        // 1. Seller's Listings
        $listings = Listing::query()
            ->where(function ($q) use ($seller) {
                $q->where('published_by_user_id', $seller->id)
                    ->orWhereHas('property', fn ($pq) => $pq->where('owner_id', $seller->id));
            })
            ->with(['property.city', 'property.subcity', 'property.images'])
            ->orderByDesc('updated_at')
            ->get();

        $activeListings = $listings->filter(fn ($l) => ($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status) === 'published');
        $inactiveListings = $listings->filter(fn ($l) => ($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status) !== 'published');
        $actionRequiredListings = $listings->filter(fn ($l) => in_array($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status, ['pending_review', 'rejected'], true));

        // 2. Inquiries & Pipeline Breakdown
        $inquiries = Inquiry::query()
            ->where('seller_id', $seller->id)
            ->with(['buyer', 'property'])
            ->orderByDesc('last_message_at')
            ->get();

        $pipelineDistribution = [
            'new' => $inquiries->where('status.value', 'new')->count() ?: $inquiries->where('status', 'new')->count(),
            'contacted' => $inquiries->where('status.value', 'contacted')->count() ?: $inquiries->where('status', 'contacted')->count(),
            'viewing' => $inquiries->where('status.value', 'viewing')->count() ?: $inquiries->where('status', 'viewing')->count(),
            'negotiating' => $inquiries->where('status.value', 'negotiating')->count() ?: $inquiries->where('status', 'negotiating')->count(),
            'offer' => $inquiries->where('status.value', 'offer')->count() ?: $inquiries->where('status', 'offer')->count(),
            'closed' => $inquiries->where('status.value', 'closed')->count() ?: $inquiries->where('status', 'closed')->count(),
            'lost' => $inquiries->where('status.value', 'lost')->count() ?: $inquiries->where('status', 'lost')->count(),
        ];

        $unreadMessagesCount = (int) $inquiries->sum('unread_seller_count');
        $totalViews = (int) $listings->sum('views_count');
        $totalFavorites = (int) $listings->sum('favorites_count');

        $data = [
            'seller' => $seller,
            'activeListingsCount' => $activeListings->count(),
            'inactiveListingsCount' => $inactiveListings->count(),
            'actionRequiredListings' => $actionRequiredListings,
            'recentListings' => $listings->take(6),
            'totalInquiries' => $inquiries->count(),
            'unreadMessagesCount' => $unreadMessagesCount,
            'pipelineDistribution' => $pipelineDistribution,
            'recentInquiries' => $inquiries->take(5),
            'totalViews' => $totalViews,
            'totalFavorites' => $totalFavorites,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('seller.dashboard', $data);
    }
}
