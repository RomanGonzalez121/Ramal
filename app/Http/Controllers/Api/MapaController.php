<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Linea;
use App\Simulacion\Celdas;
use Illuminate\Http\JsonResponse;

/**
 * Lo que el mapa dibuja y no cambia: líneas, paradas y recorridos. La posición de los colectivos va aparte.
 */
class MapaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lineas = Linea::query()
            ->orderBy('numero')
            ->with(['ramales.recorrido', 'ramales.paradas'])
            ->get();

        $paradas = [];
        foreach ($lineas as $linea) {
            foreach ($linea->ramales as $ramal) {
                foreach ($ramal->paradas as $parada) {
                    $paradas[$parada->id] ??= [
                        'id' => $parada->id,
                        'nombre' => $parada->nombre,
                        'latitud' => $parada->latitud,
                        'longitud' => $parada->longitud,
                        'lineas' => [],
                    ];
                    $paradas[$parada->id]['lineas'][$linea->numero] = $linea->numero;
                }
            }
        }

        return response()->json([
            'celdas' => Celdas::desdeConfiguracion()->configuracion(),
            'lineas' => $lineas->map(fn ($linea) => [
                'numero' => $linea->numero,
                'nombre' => $linea->nombre,
                'destino' => $linea->destino,
                'ramales' => $linea->ramales->map(fn ($ramal) => [
                    'id' => $ramal->id,
                    'sentido' => $ramal->sentido,
                    'destino' => $ramal->destino,
                    'recorrido' => $ramal->recorrido->puntos,
                    'paradas' => $ramal->paradas->pluck('id')->all(),
                ])->all(),
            ])->all(),
            'paradas' => collect($paradas)->map(fn ($p) => [...$p, 'lineas' => array_values($p['lineas'])])->values()->all(),
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
