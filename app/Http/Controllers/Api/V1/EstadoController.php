<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Posicion;
use App\Models\Simulacion;
use App\Simulacion\EstadoColectivo;
use Illuminate\Http\JsonResponse;

/**
 * Para saber si el servicio está andando, sin necesidad de token.
 */
class EstadoController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $ultimo = Simulacion::actual()->ultimo_tick_en;
        $haceS = $ultimo ? (int) abs(now()->diffInSeconds($ultimo)) : null;

        return response()->json(['data' => [
            'version' => 'v1',
            'simulacion' => $haceS !== null && $haceS < 30 ? 'activa' : 'detenida',
            'ultimo_dato_hace_s' => $haceS,
            'colectivos_en_servicio' => Posicion::where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)->count(),
        ]]);
    }
}
