<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\OpenRouterModelController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/settings', [UserController::class, 'updateSettings']);
    Route::get('/models', OpenRouterModelController::class);
    Route::post('/conversations', [ConversationController::class, 'store']);
});
