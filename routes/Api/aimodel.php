<?php

use App\Http\Controllers\Api\OpenRouterModelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {


    Route::middleware('auth.token')->group(function () {
        Route::post('/model', [OpenRouterModelController::class, 'store']);
        Route::get('/model', [OpenRouterModelController::class, 'index']);

    });
});