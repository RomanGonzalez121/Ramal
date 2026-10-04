<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Colectivo;
use App\Models\Posicion;
use App\Simulacion\EstadoColectivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dónde está cada colectivo ahora. Los que están fuera de servicio no se listan.
 */
class ColectivosController extends Controller
{
    /** Estados posibles de un colectivo en servicio. */
    public const ESTADOS = [
        EstadoColectivo::CIRCULANDO,
        EstadoColectivo::EN_PARADA,
        EstadoColectivo::EN_TERMINAL,
        EstadoColectivo::DEMORADO,
        EstadoColectivo::AVERIADO,
        EstadoColectivo::FUERA_DE_RECORRIDO,
    ];

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'linea' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS)],
        ], [
            'linea.*' => 'La línea tiene que ser un número.',
            'estado.in' => 'El estado tiene que ser uno de: '.implode(', ', self::ESTADOS).'.',
        ]);

        $posiciones = Posicion::query()
            ->with('colectivo.linea', 'ramal')
            ->where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)
            ->when(isset($datos['estado']), fn ($q) => $q->where('estado', $datos['estado']))
            ->when(isset($datos['linea']), fn ($q) => $q->whereHas('colectivo.linea', fn ($l) => $l->where('numero', $datos['linea'])))
            ->get()
            ->sortBy(fn (Posicion $p) => $p->colectivo->interno)
            ->values();

        return response()->json([
            'data' => $posiciones->map(fn (Posicion $p) => $this->presentar($p))->all(),
            'meta' => ['calculado_en' => now()->toIso8601String(), 'cantidad' => $posiciones->count()],
        ]);
    }

    public function show(Colectivo $colectivo): JsonResponse
    {
        $posicion = Posicion::with('colectivo.linea', 'ramal')->where('colectivo_id', $colectivo->id)->first();

        if (! $posicion || $posicion->estado === EstadoColectivo::FUERA_DE_SERVICIO) {
            return response()->json(['mensaje' => 'Ese colectivo está fuera de servicio.'], 404);
        }

        return response()->json(['data' => $this->presentar($posicion)]);
    }

    private function presentar(Posicion $p): array
    {
        return [
            'id' => $p->colectivo->id,
            'interno' => $p->colectivo->interno,
            'linea' => $p->colectivo->linea->numero,
            'ramal_id' => $p->ramal_id,
            'destino' => $p->ramal?->destino,
            'estado' => $p->estado,
            'latitud' => round($p->latitud, 6),
            'longitud' => round($p->longitud, 6),
            'rumbo' => $p->rumbo,
            'velocidad_kmh' => (int) round($p->velocidad_ms * 3.6),
            'actualizado_en' => $p->actualizado_en?->toIso8601String(),
        ];
    }
}
