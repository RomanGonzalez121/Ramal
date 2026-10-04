<?php

namespace App\Http\Controllers;

use App\Models\Linea;
use Illuminate\Contracts\View\View;

class LineasController extends Controller
{
    private const ANCHO = 1000;

    private const MARGEN = 40;

    public function __invoke(): View
    {
        $lineas = Linea::query()
            ->orderBy('numero')
            ->with(['ramales.recorrido', 'ramales.paradas', 'horarios'])
            ->withCount('colectivos')
            ->get();

        $paradas = $lineas->flatMap(fn ($l) => $l->ramales->flatMap(fn ($r) => $r->paradas->pluck('id')))->unique()->count();
        $metros = $lineas->sum(fn ($l) => $l->ramales->sum(fn ($r) => $r->recorrido->largo_m));

        return view('lineas', [
            'lineas' => $lineas,
            'mapa' => $this->proyectar($lineas),
            // Las cifras de la red, para el tablero de arriba.
            'cifras' => [
                'lineas' => $lineas->count(),
                'paradas' => $paradas,
                'kilometros' => (int) round($metros / 1000),
                'colectivos' => $lineas->sum('colectivos_count'),
            ],
        ]);
    }

    /**
     * Pasa longitud y latitud a coordenadas de pantalla (proyección plana corregida por la latitud,
     * suficiente para una ciudad) y arma los trazos del SVG.
     *
     * @return array{ancho: int, alto: int, trazos: array, paradas: array}
     */
    private function proyectar($lineas): array
    {
        $puntos = $lineas->flatMap(fn ($l) => $l->ramales->flatMap(fn ($r) => $r->recorrido->puntos));

        $minLon = $puntos->min(0);
        $maxLon = $puntos->max(0);
        $minLat = $puntos->min(1);
        $maxLat = $puntos->max(1);

        $correccion = cos(deg2rad(($minLat + $maxLat) / 2));
        $anchoReal = ($maxLon - $minLon) * $correccion;
        $altoReal = $maxLat - $minLat;

        $escala = (self::ANCHO - 2 * self::MARGEN) / $anchoReal;
        $alto = (int) round($altoReal * $escala + 2 * self::MARGEN);

        $x = fn (float $lon) => round(self::MARGEN + ($lon - $minLon) * $correccion * $escala, 1);
        $y = fn (float $lat) => round(self::MARGEN + ($maxLat - $lat) * $escala, 1);

        $trazos = [];
        $paradas = [];

        foreach ($lineas as $linea) {
            foreach ($linea->ramales as $ramal) {
                $trazos[] = [
                    'linea' => $linea->numero,
                    'sentido' => $ramal->sentido,
                    'd' => collect($ramal->recorrido->puntos)
                        ->map(fn ($p, $i) => ($i ? 'L' : 'M').$x($p[0]).' '.$y($p[1]))
                        ->implode(''),
                ];

                foreach ($ramal->paradas as $parada) {
                    $paradas[$parada->id] ??= [
                        'nombre' => $parada->nombre,
                        'x' => $x($parada->longitud),
                        'y' => $y($parada->latitud),
                        'lineas' => [],
                    ];
                    $paradas[$parada->id]['lineas'][$linea->numero] = $linea->numero;
                }
            }
        }

        return ['ancho' => self::ANCHO, 'alto' => $alto, 'trazos' => $trazos, 'paradas' => array_values($paradas)];
    }
}
