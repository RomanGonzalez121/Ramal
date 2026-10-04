<?php

use App\Http\Controllers\IdentidadController;
use App\Http\Controllers\LineasController;
use App\Http\Controllers\MapaPublicoController;
use App\Http\Controllers\OperadorIncidentesController;
use App\Http\Controllers\OperadorPanelController;
use App\Http\Controllers\OperadorSesionController;
use App\Http\Controllers\TeselasController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', MapaPublicoController::class)->name('mapa');
Route::get('/identidad', IdentidadController::class)->name('identidad');
Route::get('/lineas', LineasController::class)->name('lineas');
// Panel de operador: hay que ingresar con una cuenta de rol operador.
Route::middleware('guest')->group(function () {
    Route::get('/operador/ingresar', [OperadorSesionController::class, 'formulario'])->name('login');
    Route::post('/operador/ingresar', [OperadorSesionController::class, 'entrar'])->middleware('throttle:5,1');
});
Route::middleware(['auth', 'can:operar'])->group(function () {
    Route::get('/operador', [OperadorPanelController::class, 'panel'])->name('operador');
    Route::get('/operador/resumen', [OperadorPanelController::class, 'resumen'])->name('operador.resumen');
    Route::post('/operador/incidentes/{incidente}/atender', [OperadorIncidentesController::class, 'atender'])->name('operador.atender');
    Route::post('/operador/incidentes/{incidente}/resolver', [OperadorIncidentesController::class, 'resolver'])->name('operador.resolver');
});
Route::post('/operador/salir', [OperadorSesionController::class, 'salir'])->middleware('auth')->name('salir');

// Un archivo estático: sin sesión ni cookies, para que se pueda cachear.
Route::get('/mapa/parana.pmtiles', TeselasController::class)->name('teselas')
    ->withoutMiddleware([StartSession::class, EncryptCookies::class, AddQueuedCookiesToResponse::class, PreventRequestForgery::class, ShareErrorsFromSession::class]);
