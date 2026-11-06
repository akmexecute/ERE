<?php

declare(strict_types=1);

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\BuyerEngagementController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\PropertyComparisonController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', [MarketplaceController::class, 'search'])->name('marketplace.search');
Route::get('/compare', PropertyComparisonController::class)->name('marketplace.compare');

// Phase 5 Milestone 2: Public Subscriptions & Monetization (Module 33)
Route::get('/subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'index'])->name('subscriptions.index');

// Phase 5 Milestone 2: Public 360° Virtual Tours & Ad Tracking (Module 34)
Route::get('/virtual-tours/{uuid}', [\App\Http\Controllers\VirtualTourController::class, 'showPublic'])->name('virtual-tours.show');
Route::get('/ads/{uuid}/click', \App\Http\Controllers\AdClickController::class)->name('ads.click');

Route::get('/login', [OtpAuthController::class, 'showLogin'])->name('login');
Route::post('/auth/otp/send', [OtpAuthController::class, 'sendOtp']);
Route::post('/auth/otp/verify', [OtpAuthController::class, 'verifyOtp']);
Route::post('/logout', [OtpAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');
    Route::post('/host/profile', [\App\Http\Controllers\HostProfileController::class, 'store'])->name('host.profile.store');

    // National ID/Fayda & Identity Document Verification (SRS Sections 22-24, 27-29)
    Route::get('/identity/verify', [\App\Http\Controllers\IdentityVerificationController::class, 'show'])->name('identity.verify');
    Route::post('/identity/verify', [\App\Http\Controllers\IdentityVerificationController::class, 'store'])->name('identity.verify.store');
    Route::get('/identity/status', [\App\Http\Controllers\IdentityVerificationController::class, 'status'])->name('identity.verify.status');

    // Buyer Protected Contracts, Seller Identity & Transaction Accountability
    Route::get('/buyer/contracts/{contract}', [\App\Http\Controllers\BuyerContractController::class, 'show'])->name('buyer.contracts.show');
    Route::post('/buyer/contracts/{contract}/complaints', [\App\Http\Controllers\BuyerContractController::class, 'fileComplaint'])->name('buyer.contracts.complaint');

    Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::get('/listings/wizard', [ListingController::class, 'create'])->name('listings.wizard');
    Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');

    // Milestone 4: Contextual Messaging Inbox & Phone Privacy (Module 11)
    Route::get('/inbox/{uuid?}', [InquiryController::class, 'inbox'])->name('messages.inbox');
    Route::post('/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
    Route::post('/inquiries/{uuid}/messages', [InquiryController::class, 'sendMessage'])->name('inquiries.messages.store');
    Route::post('/inquiries/{uuid}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.status.update');
    Route::get('/inquiries/{uuid}/attachments/{attachmentId}', [InquiryController::class, 'downloadAttachment'])->name('inquiries.attachments.download');
    Route::post('/listings/{listing}/reveal-phone', [InquiryController::class, 'revealPhone'])->name('listings.reveal-phone');

    // Milestone 4: Buyer Engagement — Favorites, Collections, Saved Searches & Price Alerts (Module 10)
    Route::get('/favorites', [BuyerEngagementController::class, 'listFavorites'])->name('favorites.index');
    Route::post('/favorites', [BuyerEngagementController::class, 'addFavorite'])->name('favorites.store');
    Route::delete('/favorites/{listing}', [BuyerEngagementController::class, 'removeFavorite'])->name('favorites.destroy');

    Route::get('/collections', [BuyerEngagementController::class, 'listCollections'])->name('collections.index');
    Route::post('/collections', [BuyerEngagementController::class, 'storeCollection'])->name('collections.store');
    Route::get('/collections/{uuid}', [BuyerEngagementController::class, 'showCollection'])->name('collections.show');
    Route::put('/collections/{uuid}', [BuyerEngagementController::class, 'updateCollection'])->name('collections.update');
    Route::delete('/collections/{uuid}', [BuyerEngagementController::class, 'destroyCollection'])->name('collections.destroy');
    Route::post('/collections/{uuid}/items', [BuyerEngagementController::class, 'addCollectionItem'])->name('collections.items.store');
    Route::delete('/collections/{uuid}/items/{itemId}', [BuyerEngagementController::class, 'removeCollectionItem'])->name('collections.items.destroy');

    Route::get('/saved-searches', [BuyerEngagementController::class, 'listSavedSearches'])->name('saved-searches.index');
    Route::post('/saved-searches', [BuyerEngagementController::class, 'storeSavedSearch'])->name('saved-searches.store');
    Route::delete('/saved-searches/{uuid}', [BuyerEngagementController::class, 'destroySavedSearch'])->name('saved-searches.destroy');

    Route::post('/price-alerts', [BuyerEngagementController::class, 'storePriceAlert'])->name('price-alerts.store');
    Route::delete('/price-alerts/{id}', [BuyerEngagementController::class, 'destroyPriceAlert'])->name('price-alerts.destroy');

    // Milestone 4: Seller Dashboard (Module 12)
    Route::get('/seller/dashboard', SellerDashboardController::class)->name('seller.dashboard');

    // Milestone 4: Notification Preferences (Module 12)
    Route::get('/notifications/preferences', [NotificationPreferenceController::class, 'show'])->name('notifications.preferences');
    Route::post('/notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

    // Milestone 4: Customer Support Tickets (Module 12)
    Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
    Route::get('/support/tickets/create', [SupportTicketController::class, 'create'])->name('support.tickets.create');
    Route::post('/support/tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
    Route::get('/support/tickets/{uuid}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
    Route::post('/support/tickets/{uuid}/status', [SupportTicketController::class, 'updateStatus'])->name('support.tickets.status.update');

    Route::get('/documents/download/{uuid}/v/{version}', function (
        string $uuid,
        int $version,
        Request $request,
        \App\Domains\Document\Actions\GenerateDocumentSignedUrlAction $urlAction
    ) {
        $document = \App\Domains\Document\Models\Document::query()->where('uuid', $uuid)->firstOrFail();
        $targetVersion = $document->versions()->where('version_number', $version)->firstOrFail();

        // 1. Authorize user access against document privacy rules and ownership
        $urlAction->authorizeAccess($request->user(), $document);

        // 2. Record immutable download event in access logs (SRS Sections 28-29 & 144)
        \Illuminate\Support\Facades\DB::table('document_access_logs')->insert([
            'document_id' => $document->id,
            'document_version_id' => $targetVersion->id,
            'user_id' => $request->user()->id,
            'action' => 'downloaded',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);

        // 3. Verify file presence on disk before initiating download
        $disk = \Illuminate\Support\Facades\Storage::disk($targetVersion->storage_disk);
        if (! $disk->exists($targetVersion->storage_path)) {
            abort(404, 'Document file not found.');
        }

        return $disk->download($targetVersion->storage_path, $targetVersion->original_filename);
    })->middleware(['signed'])->name('documents.download');

    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');

    Route::get('/admin/languages', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'index'])->name('admin.languages');
    Route::get('/admin/languages/export', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'exportExcel'])->name('admin.languages.export');
    Route::post('/admin/languages/import-preview', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'importPreview'])->name('admin.languages.import_preview');
    Route::post('/admin/languages/import-confirm', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'importConfirm'])->name('admin.languages.import_confirm');
    Route::post('/admin/languages/update', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'update'])->name('admin.languages.update');
    Route::post('/admin/languages/add', [\App\Http\Controllers\Admin\LanguageAdminController::class, 'addPhrase'])->name('admin.languages.add');

    Route::get('/admin/security', [\App\Http\Controllers\Admin\SecurityAdminController::class, 'index'])->name('admin.security');
    Route::post('/admin/security/users/{id}/status', [\App\Http\Controllers\Admin\SecurityAdminController::class, 'toggleUserStatus'])->name('admin.security.user_status');

    Route::get('/admin/locations', [\App\Http\Controllers\Admin\LocationAdminController::class, 'index'])->name('admin.locations');
    Route::post('/admin/locations', [\App\Http\Controllers\Admin\LocationAdminController::class, 'store'])->name('admin.locations.store');
    Route::post('/admin/locations/{id}/deactivate', [\App\Http\Controllers\Admin\LocationAdminController::class, 'deactivate'])->name('admin.locations.deactivate');

    Route::get('/admin/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('admin.settings');
    Route::post('/admin/settings/translations', [\App\Http\Controllers\Admin\SettingController::class, 'updateTranslations'])->name('admin.settings.translations');
    Route::post('/admin/settings/translations/add', [\App\Http\Controllers\Admin\SettingController::class, 'addPhrase'])->name('admin.settings.translations.add');

    // Listing Moderation Workbench Routes (SRS Section 134)
    Route::get('/admin/moderation', [\App\Http\Controllers\Admin\ListingModerationController::class, 'index'])->name('admin.moderation.index');
    Route::get('/admin/moderation/{listing}', [\App\Http\Controllers\Admin\ListingModerationController::class, 'show'])->name('admin.moderation.show');
    Route::post('/admin/listings/{listing}/approve', [\App\Http\Controllers\Admin\ListingModerationController::class, 'approve'])->name('admin.listings.approve');
    Route::post('/admin/listings/{listing}/reject', [\App\Http\Controllers\Admin\ListingModerationController::class, 'reject'])->name('admin.listings.reject');
    Route::post('/admin/listings/{listing}/suspend', [\App\Http\Controllers\Admin\ListingModerationController::class, 'suspend'])->name('admin.listings.suspend');
    Route::post('/admin/listings/{listing}/request-correction', [\App\Http\Controllers\Admin\ListingModerationController::class, 'requestCorrection'])->name('admin.listings.request_correction');
    Route::post('/admin/fraud-signals/{fraudSignal}/resolve', [\App\Http\Controllers\Admin\ListingModerationController::class, 'resolveSignal'])->name('admin.fraud_signals.resolve');
    Route::post('/admin/reports/{report}/review', [\App\Http\Controllers\Admin\ListingModerationController::class, 'reviewReport'])->name('admin.reports.review');

    // Classifications & Taxonomy Admin Routes (SRS Section 8)
    Route::get('/admin/taxonomy', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'index'])->name('admin.taxonomy.index');
    Route::get('/admin/classifications', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'index'])->name('admin.classifications.index');
    Route::post('/admin/taxonomy/categories', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'storeCategory'])->name('admin.taxonomy.categories.store');
    Route::put('/admin/taxonomy/categories/{category}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'updateCategory'])->name('admin.taxonomy.categories.update');
    Route::post('/admin/taxonomy/categories/{category}/toggle', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'toggleCategory'])->name('admin.taxonomy.categories.toggle');
    Route::delete('/admin/taxonomy/categories/{category}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'destroyCategory'])->name('admin.taxonomy.categories.destroy');

    Route::post('/admin/taxonomy/property-types', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'storePropertyType'])->name('admin.taxonomy.property_types.store');
    Route::put('/admin/taxonomy/property-types/{propertyType}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'updatePropertyType'])->name('admin.taxonomy.property_types.update');
    Route::post('/admin/taxonomy/property-types/{propertyType}/toggle', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'togglePropertyType'])->name('admin.taxonomy.property_types.toggle');
    Route::delete('/admin/taxonomy/property-types/{propertyType}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'destroyPropertyType'])->name('admin.taxonomy.property_types.destroy');

    Route::post('/admin/taxonomy/listing-types', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'storeListingType'])->name('admin.taxonomy.listing_types.store');
    Route::put('/admin/taxonomy/listing-types/{listingType}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'updateListingType'])->name('admin.taxonomy.listing_types.update');
    Route::post('/admin/taxonomy/listing-types/{listingType}/toggle', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'toggleListingType'])->name('admin.taxonomy.listing_types.toggle');
    Route::delete('/admin/taxonomy/listing-types/{listingType}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'destroyListingType'])->name('admin.taxonomy.listing_types.destroy');

    Route::post('/admin/taxonomy/amenities', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'storeAmenity'])->name('admin.taxonomy.amenities.store');
    Route::put('/admin/taxonomy/amenities/{amenity}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'updateAmenity'])->name('admin.taxonomy.amenities.update');
    Route::post('/admin/taxonomy/amenities/{amenity}/toggle', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'toggleAmenity'])->name('admin.taxonomy.amenities.toggle');
    Route::delete('/admin/taxonomy/amenities/{amenity}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'destroyAmenity'])->name('admin.taxonomy.amenities.destroy');

    Route::post('/admin/taxonomy/features', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'storeFeature'])->name('admin.taxonomy.features.store');
    Route::put('/admin/taxonomy/features/{feature}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'updateFeature'])->name('admin.taxonomy.features.update');
    Route::post('/admin/taxonomy/features/{feature}/toggle', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'toggleFeature'])->name('admin.taxonomy.features.toggle');
    Route::delete('/admin/taxonomy/features/{feature}', [\App\Http\Controllers\Admin\PropertyTaxonomyAdminController::class, 'destroyFeature'])->name('admin.taxonomy.features.destroy');

    // Phase 4: Tenant Management & Profiles (Module 25)
    Route::get('/tenant/profile', [\App\Http\Controllers\TenantController::class, 'show'])->name('tenant.profile');
    Route::post('/tenant/profile', [\App\Http\Controllers\TenantController::class, 'update'])->name('tenant.profile.update');

    // Phase 4: Rental Applications (Module 25)
    Route::get('/rental-applications', [\App\Http\Controllers\RentalApplicationController::class, 'index'])->name('rental-applications.index');
    Route::get('/rental-applications/create', [\App\Http\Controllers\RentalApplicationController::class, 'create'])->name('rental-applications.create');
    Route::post('/rental-applications', [\App\Http\Controllers\RentalApplicationController::class, 'store'])->middleware('identity.verified:rental_application')->name('rental-applications.store');
    Route::get('/rental-applications/{uuid}', [\App\Http\Controllers\RentalApplicationController::class, 'show'])->name('rental-applications.show');
    Route::post('/rental-applications/{uuid}/review', [\App\Http\Controllers\RentalApplicationController::class, 'review'])->name('rental-applications.review');
    Route::post('/rental-applications/{uuid}/convert-to-lease', [\App\Http\Controllers\RentalApplicationController::class, 'convertToLease'])->name('rental-applications.convert');

    // Phase 4: Lease Contracts & Inspections (Module 26)
    Route::get('/leases', [\App\Http\Controllers\LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/{uuid}', [\App\Http\Controllers\LeaseController::class, 'show'])->name('leases.show');
    Route::post('/leases/{uuid}/sign', [\App\Http\Controllers\LeaseController::class, 'sign'])->middleware('identity.verified:sign_lease')->name('leases.sign');
    Route::post('/leases/{uuid}/inspections', [\App\Http\Controllers\LeaseController::class, 'storeInspection'])->name('leases.inspections.store');
    Route::post('/inspections/{uuid}/acknowledge', [\App\Http\Controllers\LeaseController::class, 'acknowledgeInspection'])->name('inspections.acknowledge');

    // Phase 4: Rent Schedules, Payments, Receipts & Append-Only Adjustments (Module 27)
    Route::get('/leases/{uuid}/rent-schedule', [\App\Http\Controllers\RentalFinancialController::class, 'schedule'])->name('rent.schedule');
    Route::post('/rent-schedules/{uuid}/payments', [\App\Http\Controllers\RentalFinancialController::class, 'recordPayment'])->name('rent.payments.store');
    Route::post('/rental-payments/{uuid}/confirm', [\App\Http\Controllers\RentalFinancialController::class, 'confirmPayment'])->name('rent.payments.confirm');
    Route::get('/rental-receipts/{uuid}', [\App\Http\Controllers\RentalFinancialController::class, 'receipt'])->name('rent.receipt');
    Route::post('/rental-payments/{uuid}/adjust', [\App\Http\Controllers\RentalFinancialController::class, 'adjustPayment'])->name('rent.payments.adjust');

    // Phase 4 Milestone 2: Maintenance Requests & Work Orders (Module 28)
    Route::get('/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/create', [\App\Http\Controllers\MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/maintenance', [\App\Http\Controllers\MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('/maintenance/{uuid}', [\App\Http\Controllers\MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::post('/maintenance/{uuid}/transition', [\App\Http\Controllers\MaintenanceController::class, 'transition'])->name('maintenance.transition');

    // Phase 4 Milestone 2: Enterprise Portfolio & Mandates (Module 29)
    Route::get('/portfolio/dashboard', [\App\Http\Controllers\PortfolioController::class, 'dashboard'])->name('portfolio.dashboard');
    Route::get('/portfolio/matrix', [\App\Http\Controllers\PortfolioController::class, 'matrix'])->name('portfolio.matrix');
    Route::get('/portfolio/mandates', [\App\Http\Controllers\PortfolioController::class, 'mandates'])->name('portfolio.mandates');
    Route::post('/portfolio/mandates', [\App\Http\Controllers\PortfolioController::class, 'storeMandate'])->name('portfolio.mandates.store');

    // Phase 4 Milestone 2: Database-Driven Tax & Sequential ETB Invoicing (Module 30)
    Route::get('/invoices', [\App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [\App\Http\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [\App\Http\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{uuid}', [\App\Http\Controllers\InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{uuid}/void', [\App\Http\Controllers\InvoiceController::class, 'void'])->name('invoices.void');
    Route::get('/invoices/{uuid}/print', [\App\Http\Controllers\InvoiceController::class, 'print'])->name('invoices.print');

    // Phase 5 Milestone 1: Unified Payments, Checkout, & Bank Slips (Module 31)
    Route::get('/payments/checkout', [\App\Http\Controllers\PaymentController::class, 'checkout'])->name('payments.checkout');
    Route::post('/payments/initiate', [\App\Http\Controllers\PaymentController::class, 'initiate'])->name('payments.initiate');
    Route::post('/payments/{uuid}/slip', [\App\Http\Controllers\PaymentController::class, 'submitBankSlip'])->name('payments.slip.submit');
    Route::get('/payments/{uuid}', [\App\Http\Controllers\PaymentController::class, 'show'])->name('payments.show');
    Route::get('/admin/payments/reconciliation', [\App\Http\Controllers\PaymentController::class, 'reconciliationIndex'])->name('payments.reconciliation');
    Route::post('/admin/payments/{uuid}/refund-unapplied', [\App\Http\Controllers\PaymentController::class, 'refundUnapplied'])->name('payments.refund_unapplied');
    Route::post('/admin/payments/{uuid}/confirm-disbursement', [\App\Http\Controllers\PaymentController::class, 'confirmRefundDisbursement'])->name('payments.confirm_disbursement');

    // Phase 5 Milestone 1: Host & Landlord Payout Console (Module 32)
    Route::get('/payments/payouts', [\App\Http\Controllers\HostPayoutController::class, 'index']);
    Route::get('/payouts', [\App\Http\Controllers\HostPayoutController::class, 'index'])->name('payments.payouts');
    Route::post('/payouts/{uuid}/hold', [\App\Http\Controllers\HostPayoutController::class, 'hold'])->name('payments.payouts.hold');
    Route::post('/payouts/{uuid}/release', [\App\Http\Controllers\HostPayoutController::class, 'release'])->name('payments.payouts.release');
    Route::post('/payouts/{uuid}/process', [\App\Http\Controllers\HostPayoutController::class, 'process'])->name('payments.payouts.process');

    // Phase 2 Milestone 2: Stay Operations, Check-in/out, Housekeeping, Damage Claims & Reviews (Modules 16-18)
    Route::post('/host/dashboard/preferences', [\App\Http\Controllers\HostDashboardController::class, 'updatePreferences'])->name('host.dashboard.preferences');
    Route::get('/stays/bookings/{uuid}', [\App\Http\Controllers\StayTripController::class, 'show'])->name('stays.trip');
    Route::post('/stays/bookings/{uuid}/check-in', [\App\Http\Controllers\StayTripController::class, 'checkIn'])->name('stays.check-in');
    Route::post('/stays/bookings/{uuid}/check-out', [\App\Http\Controllers\StayTripController::class, 'checkOut'])->name('stays.check-out');
    Route::post('/stays/bookings/{uuid}/claims', [\App\Http\Controllers\StayTripController::class, 'submitClaim'])->name('stays.claims.store');
    Route::post('/stays/bookings/{uuid}/reviews', [\App\Http\Controllers\StayTripController::class, 'submitReview'])->name('stays.reviews.store');
    Route::post('/cleaning-tasks/{uuid}/transition', [\App\Http\Controllers\StayTripController::class, 'transitionCleaning'])->name('cleaning.transition');

    // Phase 3 Milestone 1: Agent Profile Verification & Management (Module 19)
    Route::post('/admin/agents/{code}/verify', [\App\Http\Controllers\AgentProfileController::class, 'verify'])->name('admin.agents.verify');

    // Phase 5 Milestone 2: Subscription Checkout & Tour Management (Modules 33 & 34)
    Route::post('/subscriptions/checkout', [\App\Http\Controllers\SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');

    Route::get('/properties/{property}/virtual-tour', [\App\Http\Controllers\VirtualTourController::class, 'manage'])->name('virtual-tours.manage');
    Route::post('/properties/{property}/virtual-tour', [\App\Http\Controllers\VirtualTourController::class, 'storeTour'])->name('virtual-tours.store');
    Route::post('/virtual-tours/{tour}/scenes', [\App\Http\Controllers\VirtualTourController::class, 'storeScene'])->name('virtual-tours.scenes.store');
    Route::post('/virtual-tour-scenes/{scene}/hotspots', [\App\Http\Controllers\VirtualTourController::class, 'storeHotspot'])->name('virtual-tours.hotspots.store');
    Route::delete('/virtual-tour-scenes/{scene}', [\App\Http\Controllers\VirtualTourController::class, 'destroyScene'])->name('virtual-tours.scenes.destroy');

    // Phase 5 Milestone 2: Admin Subscription, Monetization & GIS Controls
    Route::get('/admin/subscriptions/features', [\App\Http\Controllers\Admin\SubscriptionFeatureAdminController::class, 'index'])->name('admin.subscriptions.features.index');
    Route::post('/admin/subscriptions/features', [\App\Http\Controllers\Admin\SubscriptionFeatureAdminController::class, 'store'])->name('admin.subscriptions.features.store');
    Route::put('/admin/subscriptions/features/{feature}', [\App\Http\Controllers\Admin\SubscriptionFeatureAdminController::class, 'update'])->name('admin.subscriptions.features.update');
    Route::post('/admin/subscriptions/features/{feature}/toggle', [\App\Http\Controllers\Admin\SubscriptionFeatureAdminController::class, 'toggleStatus'])->name('admin.subscriptions.features.toggle');
    Route::delete('/admin/subscriptions/features/{feature}', [\App\Http\Controllers\Admin\SubscriptionFeatureAdminController::class, 'destroy'])->name('admin.subscriptions.features.destroy');

    Route::get('/admin/subscriptions/plans', [\App\Http\Controllers\Admin\SubscriptionPlanAdminController::class, 'index'])->name('admin.subscriptions.plans.index');
    Route::post('/admin/subscriptions/plans', [\App\Http\Controllers\Admin\SubscriptionPlanAdminController::class, 'store'])->name('admin.subscriptions.plans.store');
    Route::put('/admin/subscriptions/plans/{plan}', [\App\Http\Controllers\Admin\SubscriptionPlanAdminController::class, 'update'])->name('admin.subscriptions.plans.update');
    Route::post('/admin/subscriptions/plans/{plan}/toggle', [\App\Http\Controllers\Admin\SubscriptionPlanAdminController::class, 'toggleStatus'])->name('admin.subscriptions.plans.toggle');
    Route::delete('/admin/subscriptions/plans/{plan}', [\App\Http\Controllers\Admin\SubscriptionPlanAdminController::class, 'destroy'])->name('admin.subscriptions.plans.destroy');

    Route::get('/admin/promotions', [\App\Http\Controllers\Admin\PromotionPackageAdminController::class, 'index'])->name('admin.promotions.index');
    Route::post('/admin/promotions', [\App\Http\Controllers\Admin\PromotionPackageAdminController::class, 'store'])->name('admin.promotions.store');
    Route::put('/admin/promotions/{package}', [\App\Http\Controllers\Admin\PromotionPackageAdminController::class, 'update'])->name('admin.promotions.update');
    Route::post('/admin/promotions/{package}/toggle', [\App\Http\Controllers\Admin\PromotionPackageAdminController::class, 'toggleStatus'])->name('admin.promotions.toggle');

    Route::get('/admin/advertising', [\App\Http\Controllers\Admin\AdvertisingAdminController::class, 'index'])->name('admin.advertising.index');
    Route::put('/admin/advertising/placements/{placement}', [\App\Http\Controllers\Admin\AdvertisingAdminController::class, 'updatePlacement'])->name('admin.advertising.placements.update');
    Route::post('/admin/advertising/campaigns/{campaign}/approve', [\App\Http\Controllers\Admin\AdvertisingAdminController::class, 'approveCampaign'])->name('admin.advertising.campaigns.approve');
    Route::post('/admin/advertising/campaigns/{campaign}/reject', [\App\Http\Controllers\Admin\AdvertisingAdminController::class, 'rejectCampaign'])->name('admin.advertising.campaigns.reject');

    Route::get('/admin/gis/heatmaps', [\App\Http\Controllers\Admin\GisHeatmapAdminController::class, 'index'])->name('admin.gis.heatmaps.index');
    Route::post('/admin/gis/heatmaps/recalculate', [\App\Http\Controllers\Admin\GisHeatmapAdminController::class, 'recalculate'])->name('admin.gis.heatmaps.recalculate');
});

use App\Http\Controllers\CrmDashboardController;
use App\Http\Controllers\HostDashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StayController;

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::post('/projects/{slug}/units', [ProjectController::class, 'storeUnit'])->name('projects.units.store');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/projects/{slug}/manage', [ProjectController::class, 'manage'])->name('projects.manage');

Route::get('/crm/dashboard', [CrmDashboardController::class, 'index'])->name('crm.dashboard');
Route::get('/host/dashboard', [HostDashboardController::class, 'index'])->name('host.dashboard');
Route::get('/host/profile', [\App\Http\Controllers\HostProfileController::class, 'show'])->name('host.profile');
Route::get('/agents/{code}', [\App\Http\Controllers\AgentProfileController::class, 'show'])->name('agents.show');
Route::get('/stays', [StayController::class, 'index'])->name('stays.index');
Route::get('/stays/{slug}', [StayController::class, 'show'])->name('stays.show');
Route::get('/stays/{slug}/calendar.ics', [StayController::class, 'exportIcal'])->name('stays.ical');

Route::get('/listings/{slug}', [MarketplaceController::class, 'show'])->name('listings.show');
Route::post('/listings/{listing}/report', [\App\Http\Controllers\ListingReportController::class, 'store'])->name('listings.report.store');
