<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Colectivo;
use App\Models\Linea;
use App\Simulacion\Celdas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Lo que el mapa dibuja y no cambia: líneas, paradas y recorridos. La posición de los colectivos va aparte.
 */
class MapaController extends Controller
{
    /** Lo que devuelve no cambia mientras corre la simulación (líneas, paradas y recorridos), así que se guarda 5 minutos. */
    public function __invoke(): JsonResponse
    {
        $contenido = Cache::remember('api.mapa', 300, fn () => $this->armar());

        return response()->json($contenido)->header('Cache-Control', 'public, max-age=300');
    }

    private function armar(): array
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

        return [
            'celdas' => Celdas::desdeConfiguracion()->configuracion(),
            // Los colectivos (para dibujar el pasado: el historial guarda solo el número de cada uno).
            'colectivos' => Colectivo::with('linea')->orderBy('interno')->get()->map(fn ($c) => [
                'id' => $c->id,
                'interno' => $c->interno,
                'linea' => $c->linea->numero,
            ])->all(),
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
        ];
    }
}
