<?php

namespace App\Http\Controllers\Api;

use App\Historial\Instantanea;
use App\Http\Controllers\Controller;
use App\Models\HistorialPosicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El historial para rebobinar (M8). Dos pedidos:
 *  - `rango`: desde cuándo hasta cuándo hay datos (para armar la línea de tiempo).
 *  - `index`: las fotos de una ventana de tiempo corta, en un formato compacto.
 */
class HistorialController extends Controller
{
    public function rango(): JsonResponse
    {
        $primera = HistorialPosicion::min('momento');
        $ultima = HistorialPosicion::max('momento');

        return response()->json([
            'desde' => $primera ? Carbon::parse($primera, 'UTC')->toIso8601String() : null,
            'hasta' => $ultima ? Carbon::parse($ultima, 'UTC')->toIso8601String() : null,
            'paso_s' => config('ramal.historial.paso_s'),
            'retencion_horas' => config('ramal.historial.retencion_horas'),
            'fotos' => HistorialPosicion::count(),
        ])->header('Cache-Control', 'public, max-age=5');
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after:desde'],
        ], [
            'desde.required' => 'Falta la fecha de inicio (desde).',
            'desde.date' => 'La fecha de inicio no es válida.',
            'hasta.required' => 'Falta la fecha de fin (hasta).',
            'hasta.date' => 'La fecha de fin no es válida.',
            'hasta.after' => 'La fecha de fin tiene que ser posterior a la de inicio.',
        ]);

        $desde = Carbon::parse($datos['desde'])->utc();
        $hasta = Carbon::parse($datos['hasta'])->utc();
        $maximo = (int) config('ramal.historial.ventana_max_min');

        if ($desde->diffInMinutes($hasta) > $maximo) {
            return response()->json(['mensaje' => "Se puede pedir como mucho una ventana de {$maximo} minutos por vez."], 422);
        }

        $fotos = HistorialPosicion::query()
            ->whereBetween('momento', [$desde, $hasta])
            ->orderBy('momento')
            ->get()
            ->map(fn (HistorialPosicion $foto) => [
                't' => $foto->momento->getTimestamp(),
                'c' => array_map(
                    fn (array $c) => [$c['id'], $c['ramal'], $c['lon'], $c['lat'], $c['rumbo'], array_search($c['estado'], Instantanea::ESTADOS, true)],
                    $foto->colectivos(),
                ),
            ])
            ->values();

        // Lo que ya pasó no cambia: se puede guardar en caché. La ventana que llega hasta ahora sí cambia.
        $cache = $hasta->lessThan(now()->subMinute()) ? 'public, max-age=300' : 'public, max-age=2';

        return response()->json([
            // Cada colectivo viaja como [id, ramal, longitud, latitud, rumbo, estado]; `estados` dice qué es cada número.
            'estados' => Instantanea::ESTADOS,
            'paso_s' => config('ramal.historial.paso_s'),
            'fotos' => $fotos,
        ])->header('Cache-Control', $cache);
    }
}
