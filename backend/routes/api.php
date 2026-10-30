<?php

use App\Http\Controllers\Api\Driver\AuthController;
use App\Http\Middleware\EnsureDriverApiAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('driver')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', EnsureDriverApiAccess::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
