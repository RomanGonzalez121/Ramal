<?php

use App\Http\Controllers\Api\DesviosController;
use App\Http\Controllers\Api\LlegadasController;
use App\Http\Controllers\Api\MapaController;
use App\Http\Controllers\Api\PosicionesController;
use Illuminate\Support\Facades\Route;

// Rutas de la API pública. M3 expone lo que necesita el mapa; M9 la completa con Sanctum y OpenAPI.
Route::get('/mapa', MapaController::class)->name('api.mapa');
Route::get('/posiciones', PosicionesController::class)->name('api.posiciones');
Route::get('/paradas/{parada}/llegadas', LlegadasController::class)->name('api.llegadas');
Route::get('/desvios', DesviosController::class)->name('api.desvios');
