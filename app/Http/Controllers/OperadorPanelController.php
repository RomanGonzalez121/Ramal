<?php

namespace App\Http\Controllers;

use App\Operacion\Resumen;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * El panel de operador: la pantalla y los datos que la mantienen al día.
 */
class OperadorPanelController extends Controller
{
    public function panel(): View
    {
        return view('operador.panel');
    }

    public function resumen(Resumen $resumen): JsonResponse
    {
        return response()->json($resumen->calcular())->header('Cache-Control', 'no-store');
    }
}
