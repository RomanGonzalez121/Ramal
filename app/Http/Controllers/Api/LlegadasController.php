<?php

namespace App\Http\Controllers\Api;

use App\Estimaciones\ServicioLlegadas;
use App\Http\Controllers\Controller;
use App\Models\Parada;
use Illuminate\Http\JsonResponse;

/**
 * Cuándo llegan los próximos colectivos a una parada.
 */
class LlegadasController extends Controller
{
    public function __invoke(Parada $parada, ServicioLlegadas $llegadas): JsonResponse
    {
        return response()->json($llegadas->paraParada($parada))
            ->header('Cache-Control', 'public, max-age=2');
    }
}
