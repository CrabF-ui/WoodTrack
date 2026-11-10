<?php

use App\Http\Controllers\Api\EntregaController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('api.login');

Route::middleware(['auth:sanctum', 'api.chofer'])->group(function (): void {
    Route::get('/entregas', [EntregaController::class, 'index'])->name('api.entregas.index');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
});
