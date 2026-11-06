<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Booking\Models\Booking;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\Tenant\Models\RentalApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    /**
     * Handle the authenticated multi-activity unified dashboard request.
     */
    public function __invoke(Request $request): View|JsonResponse
    {
        $user = $request->user();

        // 1. Resolve Active Tab (strictly validated against supported dashboard sections)
        $allowedTabs = ['listings', 'stays', 'purchases', 'profile'];
        $activeTab = $request->query('tab', 'listings');
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'listings';
        }

        // 2. Aggregate My Listings (Sale, Rent, Lease, Land, Development Projects)
        $userCompanyIds = $user->companies()->pluck('id');
        $listings = Listing::query()
            ->where(function ($q) use ($user, $userCompanyIds): void {
                $q->where('published_by_user_id', $user->id)
                    ->orWhereHas('property', function ($pq) use ($user, $userCompanyIds): void {
                        $pq->where('owner_id', $user->id);
                        if ($userCompanyIds->isNotEmpty()) {
                            $pq->orWhereIn('company_id', $userCompanyIds);
                        }
                    });
            })
            ->with([
                'property.category',
                'property.propertyType',
                'property.city',
                'property.subcity',
                'property.images',
                'listingType',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $listingsCounts = [
            'total' => $listings->count(),
            'published' => $listings->filter(fn ($l) => ($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status) === 'published')->count(),
            'draft' => $listings->filter(fn ($l) => ($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status) === 'draft')->count(),
            'pending' => $listings->filter(fn ($l) => in_array($l->status instanceof ListingStatus ? $l->status->value : (string) $l->status, ['pending_review', 'under_moderation'], true))->count(),
            'totalViews' => (int) $listings->sum('views_count'),
            'totalFavorites' => (int) $listings->sum('favorites_count'),
        ];

        // 3. Aggregate My Stays (Host Operations, Units, Upcoming Reservations)
        /** @var Host|null $hostProfile */
        $hostProfile = Host::query()
            ->where('user_id', $user->id)
            ->with([
                'stayProfiles.property.city',
                'stayProfiles.property.subcity',
                'stayProfiles.property.images',
                'stayProfiles.accommodationUnits',
                'stayProfiles.listing',
            ])
            ->first();

        $stayKpis = [
            'staysCount' => 0,
            'upcomingCheckins' => 0,
            'currentGuests' => 0,
            'totalRevenueEtb' => 0.0,
        ];
        $hostBookings = collect();

        if ($hostProfile) {
            $stayKpis['staysCount'] = $hostProfile->stayProfiles->count();

            $hostBookings = Booking::query()
                ->where('host_id', $hostProfile->id)
                ->with(['guest', 'property.city', 'stayProfile.listing'])
                ->orderByDesc('check_in_date')
                ->take(15)
                ->get();

            $today = now()->toDateString();
            $stayKpis['upcomingCheckins'] = $hostBookings->filter(function ($b) use ($today): bool {
                $st = is_object($b->status) && enum_exists($b->status::class) ? $b->status->value : (string) $b->status;
                $inDate = is_string($b->check_in_date) ? $b->check_in_date : $b->check_in_date->format('Y-m-d');

                return $inDate >= $today && in_array($st, ['confirmed', 'awaiting_payment', 'pending'], true);
            })->count();

            $stayKpis['currentGuests'] = $hostBookings->filter(function ($b) use ($today): bool {
                $st = is_object($b->status) && enum_exists($b->status::class) ? $b->status->value : (string) $b->status;
                $inDate = is_string($b->check_in_date) ? $b->check_in_date : $b->check_in_date->format('Y-m-d');
                $outDate = is_string($b->check_out_date) ? $b->check_out_date : $b->check_out_date->format('Y-m-d');

                return $inDate <= $today && $outDate >= $today && in_array($st, ['confirmed', 'checked_in', 'active'], true);
            })->count();

            $stayKpis['totalRevenueEtb'] = (float) $hostBookings->filter(function ($b): bool {
                $st = is_object($b->status) && enum_exists($b->status::class) ? $b->status->value : (string) $b->status;

                return in_array($st, ['confirmed', 'checked_in', 'checked_out', 'completed'], true);
            })->sum('total_amount_etb');
        }

        // 4. Aggregate My Purchases & Bookings (Consumer Transactions)
        $guestBookings = $user->guestBookings()
            ->with(['property.city', 'host', 'stayProfile.listing'])
            ->take(10)
            ->get();

        $rentalApplications = $user->rentalApplicationsAsTenant()
            ->with(['property.city', 'listing', 'landlord'])
            ->take(10)
            ->get();

        $tenantLeases = $user->leasesAsTenant()
            ->with(['property.city', 'landlord'])
            ->take(10)
            ->get();

        $unitReservations = $user->unitReservations()
            ->with(['project', 'unit'])
            ->take(10)
            ->get();

        $salesContracts = \App\Domains\Developer\Models\DeveloperSalesContract::query()
            ->where('buyer_user_id', $user->id)
            ->with(['project.company', 'unit', 'installments', 'transactions', 'complaints'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $purchaseOffers = $user->offersAsBuyer()
            ->with(['property.city', 'listing', 'seller'])
            ->take(10)
            ->get();

        $favorites = $user->favorites()
            ->with(['listing.property.city', 'listing.property.subcity', 'listing.property.images'])
            ->take(8)
            ->get();

        $consumerCounts = [
            'guestBookings' => $guestBookings->count(),
            'rentalApplications' => $rentalApplications->count(),
            'leases' => $tenantLeases->count(),
            'reservations' => $unitReservations->count(),
            'salesContracts' => $salesContracts->count(),
            'offers' => $purchaseOffers->count(),
            'favorites' => $favorites->count(),
        ];

        // 5. Aggregate Profile & Business Entities
        $companies = $user->companies()->with(['branches'])->get();
        $agentProfile = $user->agentProfile()->first();
        $developerProfile = $user->developerProfile()->first();
        $tenantProfile = $user->tenantProfile()->first();
        $auditLogs = $user->auditLogs()->take(6)->get();

        $data = [
            'user' => $user,
            'activeTab' => $activeTab,
            'listings' => $listings,
            'listingsCounts' => $listingsCounts,
            'hostProfile' => $hostProfile,
            'stayKpis' => $stayKpis,
            'hostBookings' => $hostBookings,
            'guestBookings' => $guestBookings,
            'rentalApplications' => $rentalApplications,
            'tenantLeases' => $tenantLeases,
            'unitReservations' => $unitReservations,
            'salesContracts' => $salesContracts,
            'purchaseOffers' => $purchaseOffers,
            'favorites' => $favorites,
            'consumerCounts' => $consumerCounts,
            'companies' => $companies,
            'agentProfile' => $agentProfile,
            'developerProfile' => $developerProfile,
            'tenantProfile' => $tenantProfile,
            'auditLogs' => $auditLogs,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('dashboard.index', $data);
    }
}
