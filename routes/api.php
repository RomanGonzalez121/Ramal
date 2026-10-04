<?php

use App\Http\Controllers\Api\DesviosController;
use App\Http\Controllers\Api\HistorialController;
use App\Http\Controllers\Api\LlegadasController;
use App\Http\Controllers\Api\MapaController;
use App\Http\Controllers\Api\PosicionesController;
use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

// Rutas internas: las usa el propio sitio (mapa y rebobinado). No tienen garantía de estabilidad; la API pública es /v1.
Route::get('/mapa', MapaController::class)->name('api.mapa');
Route::get('/posiciones', PosicionesController::class)->name('api.posiciones');
Route::get('/paradas/{parada}/llegadas', LlegadasController::class)->name('api.llegadas');
Route::get('/desvios', DesviosController::class)->name('api.desvios');
Route::get('/historial/rango', [HistorialController::class, 'rango'])->name('api.historial.rango');
Route::get('/historial', [HistorialController::class, 'index'])->name('api.historial');

// API pública v1 (M9): documentada en OpenAPI (docs/openapi.yaml, página /api/docs). Cada token tiene su cupo por minuto.
Route::prefix('v1')->name('v1.')->group(function () {
    Route::get('/estado', V1\EstadoController::class)->name('estado');

    Route::middleware(['auth:sanctum', 'abilities:leer', 'throttle:api-v1'])->group(function () {
        Route::get('/lineas', [V1\LineasController::class, 'index'])->name('lineas');
        Route::get('/lineas/{linea:numero}', [V1\LineasController::class, 'show'])->name('lineas.mostrar');
        Route::get('/paradas', [V1\ParadasController::class, 'index'])->name('paradas');
        Route::get('/paradas/{parada}', [V1\ParadasController::class, 'show'])->name('paradas.mostrar');
        Route::get('/paradas/{parada}/llegadas', [V1\ParadasController::class, 'llegadas'])->name('paradas.llegadas');
        Route::get('/colectivos', [V1\ColectivosController::class, 'index'])->name('colectivos');
        Route::get('/colectivos/{colectivo}', [V1\ColectivosController::class, 'show'])->name('colectivos.mostrar');
        Route::get('/gtfs-rt/posiciones', [V1\GtfsRealtimeController::class, 'posiciones'])->name('gtfs.posiciones');
    });
});
