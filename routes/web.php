<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\EntregaEvidenciaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware(['auth', 'rol:'.User::ROL_ADMIN.','.User::ROL_ENCARGADO_INVENTARIO]);

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'rol:'.User::ROL_ADMIN.','.User::ROL_ENCARGADO_INVENTARIO])
    ->name('dashboard');

Route::middleware(['auth', 'rol:'.User::ROL_ADMIN])->group(function () {
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'usuario'])->only(['index', 'create', 'store', 'edit', 'update'])->whereNumber('usuario');
    Route::patch('usuarios/{usuario}/desactivar', [UserController::class, 'deactivate'])->whereNumber('usuario')->name('usuarios.desactivar');
    Route::patch('usuarios/{usuario}/reactivar', [UserController::class, 'reactivate'])->whereNumber('usuario')->name('usuarios.reactivar');
    Route::patch('usuarios/{usuario}/password', [UserController::class, 'resetPassword'])->whereNumber('usuario')->name('usuarios.password');
    Route::get('pedidos-papelera', [PedidoController::class, 'trashed'])->name('pedidos.trashed');
    Route::post('pedidos/{id}/restore', [PedidoController::class, 'restore'])->name('pedidos.restore');
    Route::resource('pedidos', PedidoController::class)->except(['show']);
    Route::get('entregas/{entrega}/evidencia', [EntregaEvidenciaController::class, 'show'])->whereNumber('entrega')->name('entregas.evidencia');
    Route::get('entregas/{entrega}/evidencia/firma', [EntregaEvidenciaController::class, 'firma'])->whereNumber('entrega')->name('entregas.evidencia.firma');
    Route::get('entregas/{entrega}/evidencia/fotos/{foto}', [EntregaEvidenciaController::class, 'foto'])->whereNumber('entrega')->whereNumber('foto')->name('entregas.evidencia.foto');
    Route::resource('entregas', EntregaController::class)->except(['show', 'destroy']);
    Route::post('clientes/{id}/restore', [ClienteController::class, 'restore'])->name('clientes.restore');
    Route::resource('clientes', ClienteController::class)->except(['show']);
});

Route::middleware(['auth', 'rol:'.User::ROL_ADMIN.','.User::ROL_ENCARGADO_INVENTARIO])->group(function () {
    Route::get('/inventario', [MovimientoInventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/materiales', [MaterialController::class, 'index'])->name('inventario.materiales.index');
    Route::get('/inventario/materiales/create', [MaterialController::class, 'create'])->name('inventario.materiales.create');
    Route::post('/inventario/materiales', [MaterialController::class, 'store'])->name('inventario.materiales.store');
    Route::get('/inventario/materiales/{material}/edit', [MaterialController::class, 'edit'])->whereNumber('material')->name('inventario.materiales.edit');
    Route::put('/inventario/materiales/{material}', [MaterialController::class, 'update'])->whereNumber('material')->name('inventario.materiales.update');
    Route::delete('/inventario/materiales/{material}', [MaterialController::class, 'destroy'])->whereNumber('material')->name('inventario.materiales.destroy');
    Route::post('/inventario/materiales/{id}/restore', [MaterialController::class, 'restore'])->whereNumber('id')->name('inventario.materiales.restore');
    Route::get('/inventario/movimientos', [MovimientoInventarioController::class, 'movimientos'])->name('inventario.movimientos.index');
    Route::get('/inventario/movimientos/create', [MovimientoInventarioController::class, 'create'])->name('inventario.movimientos.create');
    Route::post('/inventario/movimientos', [MovimientoInventarioController::class, 'store'])->name('inventario.movimientos.store');
    Route::delete('/inventario/movimientos/{id}', [MovimientoInventarioController::class, 'destroy'])->name('inventario.movimientos.destroy');
    Route::post('/inventario/movimientos/{id}/restore', [MovimientoInventarioController::class, 'restore'])->name('inventario.movimientos.restore');
});

Route::middleware(['auth', 'rol:'.User::ROL_ADMIN.','.User::ROL_ENCARGADO_INVENTARIO])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
