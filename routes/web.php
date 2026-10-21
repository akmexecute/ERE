<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketplaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', [MarketplaceController::class, 'search'])->name('marketplace.search');

Route::get('/login', [OtpAuthController::class, 'showLogin'])->name('login');
Route::post('/auth/otp/send', [OtpAuthController::class, 'sendOtp']);
Route::post('/auth/otp/verify', [OtpAuthController::class, 'verifyOtp']);
Route::post('/logout', [OtpAuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'auditLogs' => $user->auditLogs()->take(10)->get(),
        ]);
    })->name('dashboard');

    Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::get('/listings/wizard', [ListingController::class, 'create'])->name('listings.wizard');
    Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');

    Route::get('/inbox', function () {
        return view('messages.inbox');
    })->name('messages.inbox');

    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');
});

Route::get('/stays/{slug}', function (string $slug) {
    return view('stays.show');
})->name('stays.show');

Route::get('/listings/{slug}', [MarketplaceController::class, 'show'])->name('listings.show');
