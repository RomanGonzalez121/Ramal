<?php

namespace App\Support;

/**
 * Cálculos geográficos en PHP puro. Los puntos son [longitud, latitud] (el orden de GeoJSON).
 *
 * Se eligió guardar los trazados como JSON y calcular acá (ver docs/decisiones.md, M1-1):
 * lo que Ramal necesita es la distancia a lo largo del recorrido, no consultas espaciales.
 */
class Geo
{
    private const RADIO_TIERRA_M = 6371008.8;

    /** Distancia entre dos puntos sobre la esfera (fórmula de haversine), en metros. */
    public static function distanciaMetros(array $a, array $b): float
    {
        [$lon1, $lat1] = array_map('deg2rad', $a);
        [$lon2, $lat2] = array_map('deg2rad', $b);

        $h = sin(($lat2 - $lat1) / 2) ** 2
            + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 2 * self::RADIO_TIERRA_M * asin(min(1.0, sqrt($h)));
    }

    /**
     * Metros recorridos hasta cada punto del trazado. El primero vale 0.
     *
     * @param  array<int, array{0: float, 1: float}>  $puntos
     * @return array<int, float>
     */
    public static function distanciasAcumuladas(array $puntos): array
    {
        $acumuladas = [0.0];

        for ($i = 1; $i < count($puntos); $i++) {
            $acumuladas[] = $acumuladas[$i - 1] + self::distanciaMetros($puntos[$i - 1], $puntos[$i]);
        }

        return $acumuladas;
    }

    /**
     * Proyecta un punto sobre el trazado: cuánto se aleja de él y a cuántos metros del inicio cae.
     * `$desdeMetros` obliga a buscar de ahí en adelante, para que las paradas queden en orden
     * aunque el recorrido pase dos veces por la misma esquina.
     *
     * @param  array{0: float, 1: float}  $punto
     * @param  array<int, array{0: float, 1: float}>  $trazado
     * @param  array<int, float>  $acumuladas
     * @return array{distancia_al_trazado_m: float, a_lo_largo_m: float}
     */
    public static function proyectar(array $punto, array $trazado, array $acumuladas, float $desdeMetros = 0.0): array
    {
        $mejor = null;

        // Plano local en metros, centrado en el punto: alcanza para tramos de cuadras.
        $escalaX = cos(deg2rad($punto[1])) * M_PI / 180 * self::RADIO_TIERRA_M;
        $escalaY = M_PI / 180 * self::RADIO_TIERRA_M;

        for ($i = 1; $i < count($trazado); $i++) {
            if ($acumuladas[$i] < $desdeMetros) {
                continue;
            }

            [$a, $b] = [$trazado[$i - 1], $trazado[$i]];

            $ax = ($a[0] - $punto[0]) * $escalaX;
            $ay = ($a[1] - $punto[1]) * $escalaY;
            $bx = ($b[0] - $punto[0]) * $escalaX;
            $by = ($b[1] - $punto[1]) * $escalaY;

            $dx = $bx - $ax;
            $dy = $by - $ay;
            $largo2 = $dx * $dx + $dy * $dy;
            $t = $largo2 > 0 ? max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $largo2)) : 0.0;

            $distancia = hypot($ax + $t * $dx, $ay + $t * $dy);
            $aLoLargo = $acumuladas[$i - 1] + $t * ($acumuladas[$i] - $acumuladas[$i - 1]);

            if ($mejor === null || $distancia < $mejor['distancia_al_trazado_m']) {
                $mejor = ['distancia_al_trazado_m' => $distancia, 'a_lo_largo_m' => max($aLoLargo, $desdeMetros)];
            }
        }

        return $mejor ?? ['distancia_al_trazado_m' => INF, 'a_lo_largo_m' => $desdeMetros];
    }
}
