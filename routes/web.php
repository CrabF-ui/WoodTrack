<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard'); // -- ¿Por qué se quitó return view('dashboard');? 
        // --Porque al utilizar un controlador, esa instrucción deja de estar directamente en routes/web.php y pasa al método index() de DashboardController.



Route::middleware('auth')->group(function () {
    Route::get('/inventario', [MovimientoInventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/movimientos', [MovimientoInventarioController::class, 'movimientos'])->name('inventario.movimientos.index');
    Route::get('/inventario/movimientos/create', [MovimientoInventarioController::class, 'create'])->name('inventario.movimientos.create');
    Route::post('/inventario/movimientos', [MovimientoInventarioController::class, 'store'])->name('inventario.movimientos.store');
    Route::delete('/inventario/movimientos/{id}', [MovimientoInventarioController::class, 'destroy'])->name('inventario.movimientos.destroy');
    Route::post('/inventario/movimientos/{id}/restore', [MovimientoInventarioController::class, 'restore'])->name('inventario.movimientos.restore');
    Route::get('pedidos-papelera', [PedidoController::class, 'trashed'])->name('pedidos.trashed');
    Route::post('pedidos/{id}/restore', [PedidoController::class, 'restore'])->name('pedidos.restore');
    Route::resource('pedidos', PedidoController::class)->except(['show']);
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
