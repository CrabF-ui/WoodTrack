<?php

use App\Http\Controllers\Api\EntregaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'api.chofer'])->group(function (): void {
    Route::get('/entregas', [EntregaController::class, 'index'])->name('api.entregas.index');
});
