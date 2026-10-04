<?php

namespace App\Http\Controllers\Api\V1;

use App\Estimaciones\ServicioLlegadas;
use App\Http\Controllers\Controller;
use App\Models\Parada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las paradas, con filtro por línea o por cercanía, y la cuenta regresiva de cada una.
 */
class ParadasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'linea' => ['nullable', 'integer', 'min:1'],
            'cerca' => ['nullable', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'radio_m' => ['nullable', 'integer', 'min:50', 'max:5000'],
        ], [
            'linea.*' => 'La línea tiene que ser un número.',
            'cerca.regex' => 'La búsqueda por cercanía tiene que ser latitud,longitud (por ejemplo -31.7333,-60.5287).',
            'radio_m.*' => 'El radio tiene que estar entre 50 y 5000 metros.',
        ]);

        $paradas = Parada::query()->with('ramales.linea')->orderBy('nombre')->get();

        if (isset($datos['linea'])) {
            $paradas = $paradas->filter(fn (Parada $p) => $p->ramales->contains(fn ($r) => $r->linea->numero === (int) $datos['linea']));
        }

        $distancias = [];
        if (isset($datos['cerca'])) {
            [$lat, $lon] = array_map('floatval', explode(',', $datos['cerca']));
            $radio = (int) ($datos['radio_m'] ?? 500);

            foreach ($paradas as $p) {
                $distancias[$p->id] = $this->distanciaM($lat, $lon, $p->latitud, $p->longitud);
            }

            $paradas = $paradas->filter(fn (Parada $p) => $distancias[$p->id] <= $radio)
                ->sortBy(fn (Parada $p) => $distancias[$p->id]);
        }

        return response()->json(['data' => $paradas->values()->map(fn (Parada $p) => [
            ...$this->resumen($p),
            ...(isset($distancias[$p->id]) ? ['distancia_m' => (int) round($distancias[$p->id])] : []),
        ])->all()]);
    }

    public function show(Parada $parada): JsonResponse
    {
        $parada->load('ramales.linea');

        return response()->json(['data' => $this->resumen($parada)]);
    }

    public function llegadas(Parada $parada, ServicioLlegadas $llegadas): JsonResponse
    {
        return response()->json(['data' => $llegadas->paraParada($parada)]);
    }

    private function resumen(Parada $parada): array
    {
        return [
            'id' => $parada->id,
            'nombre' => $parada->nombre,
            'latitud' => $parada->latitud,
            'longitud' => $parada->longitud,
            'lineas' => $parada->ramales->map(fn ($r) => $r->linea->numero)->unique()->sort()->values()->all(),
        ];
    }

    /** Distancia entre dos puntos en metros (fórmula del haversine). */
    private function distanciaM(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radio = 6371000;
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 2 * $radio * asin(min(1, sqrt($a)));
    }
}
