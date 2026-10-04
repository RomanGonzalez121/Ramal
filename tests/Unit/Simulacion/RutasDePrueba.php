<?php

namespace Tests\Unit\Simulacion;

use App\Simulacion\RutaSimulada;
use App\Support\Geo;

/**
 * Una avenida recta de 3 km hacia el este con paradas cada kilómetro (0, 1000, 2000 y 3000 m),
 * y la misma de vuelta. Sirve para probar el simulador y las estimaciones sin base de datos.
 *
 * Cada tramo entre paradas tiene un camino alternativo: sube 200 m hacia el norte, avanza 1 km y baja
 * otros 200 m (1400 m en total, o sea 1,4 veces el tramo normal).
 */
trait RutasDePrueba
{
    /** Latitud de la avenida. */
    private const LATITUD = -31.7;

    /** Cuánto se separa el camino alternativo de la avenida, en grados (unos 200 m). */
    private const SALTO_LATITUD = 0.0018;

    /** @return array<int, RutaSimulada> ramal 1 = ida, ramal 2 = vuelta, con camino alternativo en cada tramo */
    private function rutas(): array
    {
        return $this->armarRutas(conDesvios: true);
    }

    /** @return array<int, RutaSimulada> las mismas rutas, sin ningún camino alternativo */
    private function rutasSinDesvios(): array
    {
        return $this->armarRutas(conDesvios: false);
    }

    /** @return array<int, RutaSimulada> */
    private function armarRutas(bool $conDesvios): array
    {
        $grados = 1000 / (111_195 * cos(deg2rad(self::LATITUD)));  // grados de longitud por kilómetro
        $ida = [];
        for ($i = 0; $i <= 30; $i++) {
            $ida[] = [-60.55 + $i * $grados / 10, self::LATITUD];
        }
        $distancias = array_map(fn ($i) => $i * 100.0, range(0, 30));

        $paradas = fn (string $prefijo) => array_map(
            fn ($k) => ['orden' => $k + 1, 'distancia_m' => $k * 1000.0, 'nombre' => "{$prefijo}{$k}"],
            range(0, 3),
        );

        $puntosIda = [$ida[0], $ida[10], $ida[20], $ida[30]];

        return [
            1 => new RutaSimulada(1, 2, $ida, $distancias, $paradas('I'), $conDesvios ? $this->caminos($puntosIda) : []),
            2 => new RutaSimulada(2, 1, array_reverse($ida), $distancias, $paradas('V'), $conDesvios ? $this->caminos(array_reverse($puntosIda)) : []),
        ];
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $paradas  los puntos de las cuatro paradas, en orden
     * @return array<int, array{puntos: array, distancias: array}>
     */
    private function caminos(array $paradas): array
    {
        $caminos = [];

        for ($orden = 1; $orden <= 3; $orden++) {
            [$a, $b] = [$paradas[$orden - 1], $paradas[$orden]];
            $norte = self::LATITUD + self::SALTO_LATITUD;
            $puntos = [$a, [$a[0], $norte], [$b[0], $norte], $b];

            $caminos[$orden] = ['puntos' => $puntos, 'distancias' => Geo::distanciasAcumuladas($puntos)];
        }

        return $caminos;
    }
}
