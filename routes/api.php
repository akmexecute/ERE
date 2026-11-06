<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\HostDashboardController;
use App\Http\Controllers\StayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/locations/children', [LocationApiController::class, 'children']);
    Route::get('/gis/reverse-geocode', [LocationApiController::class, 'reverseGeocode']);

    // Phase 3 Modules 20 & 21: Negotiation & Viewing Scheduler
    Route::post('/listings/{listing}/negotiate', [\App\Http\Controllers\Api\v1\NegotiationController::class, 'negotiate'])->middleware('identity.verified:make_offer');
    Route::match(['put', 'patch'], '/offers/{offer}', [\App\Http\Controllers\Api\v1\NegotiationController::class, 'update']);
    Route::get('/listings/{listing}/offers', [\App\Http\Controllers\Api\v1\NegotiationController::class, 'index']);
    Route::post('/viewings', [\App\Http\Controllers\Api\v1\ViewingSchedulerController::class, 'store']);
    Route::get('/viewings', [\App\Http\Controllers\Api\v1\ViewingSchedulerController::class, 'index']);
    Route::patch('/viewings/{id}/status', [\App\Http\Controllers\Api\v1\ViewingSchedulerController::class, 'updateStatus']);

    // Phase 3 Module 24: Unit Reservations & Contracts
    Route::post('/units/{unit}/reserve', [\App\Http\Controllers\Api\v1\ReservationController::class, 'reserve'])->middleware('identity.verified:reserve_unit');
    Route::post('/reservations/{reservation}/convert', [\App\Http\Controllers\Api\v1\ReservationController::class, 'convert']);
    Route::get('/reservations/{id}', [\App\Http\Controllers\Api\v1\ReservationController::class, 'show']);
    Route::post('/reservations/{id}/cancel', [\App\Http\Controllers\Api\v1\ReservationController::class, 'cancel']);

    // Phase 3 Modules 22, 23 & 24: Construction Stages, Developer Accounts & Payments
    Route::get('/projects/{project}/payment-accounts', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'getPaymentAccounts']);
    Route::post('/developer/payment-accounts', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'storePaymentAccount']);
    Route::post('/contracts/{contract}/payments/manual', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'submitManualPayment']);
    Route::post('/payments/{payment}/verify', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'verifyPayment']);
    Route::post('/projects/{project}/milestones', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'configureMilestones']);
    Route::post('/projects/{project}/payment-plans', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'storePaymentPlan']);
    Route::post('/contracts/{contract}/amend', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'amendContract']);
    Route::post('/contracts/{contract}/amendments/{amendment}/accept', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'acceptAmendment']);
    Route::post('/contracts/{contract}/amendments/{amendment}/reject', [\App\Http\Controllers\Api\v1\DeveloperProjectApiController::class, 'rejectAmendment']);
});

Route::post('/stays/quote', [StayController::class, 'calculateQuote']);
Route::post('/stays/refund-preview', [StayController::class, 'calculateRefund']);
Route::post('/stays/hold', [StayController::class, 'createHold'])->middleware('identity.verified:book_stay');
Route::get('/stays/{slug}/calendar', [StayController::class, 'getCalendar']);
Route::post('/stays/confirm', [StayController::class, 'confirmBooking'])->middleware('identity.verified:book_stay');
Route::post('/stays/cancel', [StayController::class, 'cancelBooking']);

Route::post('/host/profile', [\App\Http\Controllers\HostProfileController::class, 'store']);
Route::put('/host/profile/{uuid}', [\App\Http\Controllers\HostProfileController::class, 'update']);
Route::post('/hosts', [\App\Http\Controllers\HostProfileController::class, 'store']);
Route::put('/hosts/{uuid}', [\App\Http\Controllers\HostProfileController::class, 'update']);

Route::post('/host/settle-deposit', [HostDashboardController::class, 'settleDeposit']);
Route::post('/host/evaluate-review', [HostDashboardController::class, 'evaluateReview']);
Route::post('/host/advance-cleaning', [HostDashboardController::class, 'advanceCleaning']);

// Phase 5 Milestone 1: Authenticated & Idempotent Webhook Processor (Module 31)
Route::post('/webhooks/payments/{provider}', [\App\Http\Controllers\PaymentWebhookController::class, 'handle'])->name('api.webhooks.payments');
Route::post('/payments/initiate', [\App\Http\Controllers\PaymentController::class, 'initiate'])->name('api.payments.initiate');

// Phase 5 Milestone 2: Advanced PostGIS Spatial Heatmaps (Module 34)
Route::get('/gis/neighborhood-heatmap', [\App\Http\Controllers\Api\GisHeatmapApiController::class, 'index'])->name('api.gis.neighborhood_heatmap');
