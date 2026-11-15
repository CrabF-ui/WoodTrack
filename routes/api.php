<?php

use App\Http\Controllers\Api\EntregaController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('api.login');

Route::middleware(['auth:sanctum', 'api.chofer'])->group(function (): void {
    Route::get('/entregas', [EntregaController::class, 'index'])->name('api.entregas.index');
    Route::patch('/entregas/{entrega}/estado', [EntregaController::class, 'cambiarEstado'])
        ->whereNumber('entrega')
        ->name('api.entregas.estado');
    Route::post('/entregas/{entrega}/incidencia', [EntregaController::class, 'registrarIncidencia'])
        ->whereNumber('entrega')
        ->name('api.entregas.incidencia');
    Route::post('/entregas/{entrega}/entregar', [EntregaController::class, 'entregar'])
        ->whereNumber('entrega')
        ->name('api.entregas.entregar');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
});
