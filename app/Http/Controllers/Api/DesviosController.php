<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Desvio;
use App\Models\Posicion;
use Illuminate\Http\JsonResponse;

/**
 * Los desvíos que hay en curso ahora: por qué calles están yendo los colectivos que salieron del recorrido.
 * El mapa los dibuja como un camino punteado.
 */
class DesviosController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $posiciones = Posicion::with('colectivo.linea')->whereNotNull('desvio_orden')->get();

        $caminos = Desvio::query()
            ->whereIn('ramal_id', $posiciones->pluck('ramal_id'))
            ->get()
            ->keyBy(fn (Desvio $d) => "{$d->ramal_id}-{$d->desde_orden}");

        $desvios = $posiciones
            ->map(fn (Posicion $p) => [
                'interno' => $p->colectivo->interno,
                'linea' => $p->colectivo->linea->numero,
                'puntos' => $caminos["{$p->ramal_id}-{$p->desvio_orden}"]?->puntos,
            ])
            ->filter(fn (array $d) => $d['puntos'] !== null)
            ->values();

        return response()->json(['desvios' => $desvios])->header('Cache-Control', 'public, max-age=3');
    }
}
