<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulacion;
use App\Simulacion\Celdas;
use App\Simulacion\ServicioSimulacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dónde está cada colectivo ahora. Sirve para dos cosas: que el mapa muestre colectivos al instante
 * (sin esperar el próximo mensaje de tiempo real) y de respaldo por consulta periódica si el WebSocket no anda.
 */
class PosicionesController extends Controller
{
    public function __invoke(Request $request, ServicioSimulacion $servicio): JsonResponse
    {
        $vista = null;

        if ($request->filled('vista')) {
            $partes = array_map('floatval', explode(',', (string) $request->query('vista')));

            if (count($partes) !== 4) {
                return response()->json(['mensaje' => 'La vista tiene que ser oeste,sur,este,norte.'], 422);
            }

            $vista = $partes;
        }

        return response()->json([
            'tick' => Simulacion::actual()->tick,
            'celdas' => $vista ? Celdas::desdeConfiguracion()->queCruzan($vista) : Celdas::desdeConfiguracion()->todas(),
            'colectivos' => $servicio->instantanea($vista),
        ]);
    }
}
