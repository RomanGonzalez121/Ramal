<?php

namespace App\Http\Controllers\Api\V1;

use App\Api\GtfsRealtime\PosicionesDeVehiculos;
use App\Http\Controllers\Controller;
use App\Models\Posicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El feed de posiciones en GTFS Realtime. Por defecto va en protobuf, que es lo que esperan las aplicaciones;
 * con `?formato=json` se ve el mismo contenido como texto, para mirarlo o depurarlo.
 */
class GtfsRealtimeController extends Controller
{
    public function posiciones(Request $request, PosicionesDeVehiculos $feed): Response|JsonResponse
    {
        $datos = $request->validate([
            'formato' => ['nullable', 'in:protobuf,json'],
        ], [
            'formato.in' => 'El formato tiene que ser protobuf o json.',
        ]);

        $arreglo = $feed->comoArreglo(
            Posicion::with('colectivo.linea', 'ramal.paradas')->get(),
            now(),
        );

        if (($datos['formato'] ?? 'protobuf') === 'json') {
            return response()->json($arreglo);
        }

        return response($feed->codificar($arreglo), 200, [
            'Content-Type' => 'application/x-protobuf',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
