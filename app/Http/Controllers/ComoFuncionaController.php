<?php

namespace App\Http\Controllers;

use App\Estimaciones\Estimador;
use App\Historial\Instantanea;
use App\Models\Colectivo;
use App\Models\Linea;
use App\Models\Parada;
use App\Simulacion\Simulador;
use Illuminate\Contracts\View\View;

/**
 * M10: la página que explica qué es simulado y qué es real. Los números salen de las mismas constantes que usa el
 * sistema (el estimador, el historial, la grilla del tiempo real), así que la explicación no se puede desactualizar.
 */
class ComoFuncionaController extends Controller
{
    public function __invoke(): View
    {
        $historial = config('ramal.historial');
        $bytesPorFoto = Colectivo::count() * Instantanea::BYTES_POR_COLECTIVO;
        $fotosPorHora = 3600 / $historial['paso_s'];
        $ciudad = config('ramal.ciudad');

        return view('como-funciona', [
            'cifras' => [
                'lineas' => Linea::count(),
                'paradas' => Parada::count(),
                'colectivos' => Colectivo::count(),
                'intervalo_s' => config('ramal.intervalo_s'),
            ],
            'estimador' => [
                'parada_s' => Estimador::PARADA_PROMEDIO_S,
                'terminal_s' => Estimador::TERMINAL_PROMEDIO_S,
                'arreglo_s' => Estimador::ARREGLO_TIPICO_S,
                'velocidad_minima_ms' => Estimador::VELOCIDAD_MINIMA_MS,
                'factor_desvio' => Estimador::FACTOR_VELOCIDAD_EN_DESVIO,
            ],
            'incidentes' => [
                'demora_horas' => round(1 / (Simulador::TASAS['demora'] * 3600)),
                'desvio_horas' => round(1 / (Simulador::TASAS['desvio'] * 3600)),
                'falla_horas' => round(1 / (Simulador::TASAS['falla'] * 3600)),
            ],
            'historial' => [
                'paso_s' => $historial['paso_s'],
                'retencion_horas' => $historial['retencion_horas'],
                'bytes_por_colectivo' => Instantanea::BYTES_POR_COLECTIVO,
                'bytes_por_foto' => $bytesPorFoto,
                'mb_retenidos' => round($bytesPorFoto * $fotosPorHora * $historial['retencion_horas'] / 1_000_000, 1),
            ],
            'grilla' => ['columnas' => $ciudad['columnas'], 'filas' => $ciudad['filas']],
        ]);
    }
}
