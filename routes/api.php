<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LocationApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/locations/children', [LocationApiController::class, 'children']);
    Route::get('/gis/reverse-geocode', [LocationApiController::class, 'reverseGeocode']);
});
