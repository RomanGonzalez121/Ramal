<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Linea;
use Illuminate\Http\JsonResponse;

/**
 * Las líneas y sus ramales. El detalle de una línea trae las paradas en orden y el trazado sobre las calles.
 */
class LineasController extends Controller
{
    public function index(): JsonResponse
    {
        $lineas = Linea::query()->orderBy('numero')->with('ramales.paradas')->get();

        return response()->json(['data' => $lineas->map(fn (Linea $linea) => [
            'numero' => $linea->numero,
            'nombre' => $linea->nombre,
            'destino' => $linea->destino,
            'ramales' => $linea->ramales->map(fn ($ramal) => [
                'id' => $ramal->id,
                'sentido' => $ramal->sentido,
                'destino' => $ramal->destino,
                'cantidad_paradas' => $ramal->paradas->count(),
            ])->all(),
        ])->all()]);
    }

    public function show(Linea $linea): JsonResponse
    {
        $linea->load(['ramales.paradas', 'ramales.recorrido', 'horarios']);

        return response()->json(['data' => [
            'numero' => $linea->numero,
            'nombre' => $linea->nombre,
            'destino' => $linea->destino,
            'horarios' => $linea->horarios->map(fn ($h) => [
                'dia' => $h->dia,
                'desde' => substr($h->desde, 0, 5),
                'hasta' => substr($h->hasta, 0, 5),
                'frecuencia_min' => $h->frecuencia_min,
            ])->values()->all(),
            'ramales' => $linea->ramales->map(fn ($ramal) => [
                'id' => $ramal->id,
                'sentido' => $ramal->sentido,
                'destino' => $ramal->destino,
                'paradas' => $ramal->paradas->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'orden' => $p->pivot->orden,
                    'distancia_m' => (int) round($p->pivot->distancia_m),
                    'latitud' => $p->latitud,
                    'longitud' => $p->longitud,
                ])->all(),
                'recorrido' => ['type' => 'LineString', 'coordinates' => $ramal->recorrido?->puntos ?? []],
            ])->all(),
        ]]);
    }
}
