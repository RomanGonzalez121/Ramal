<?php

namespace App\Simulacion;

/**
 * Lo que el simulador necesita saber de un ramal: su trazado con metros acumulados, sus paradas y los
 * caminos alternativos entre paradas. Es un objeto simple, sin base de datos, para poder probar el simulador
 * en PHP puro.
 *
 * Cómo se representa un desvío: el colectivo sigue avanzando por la "distancia virtual" del ramal normal
 * (la que usan las paradas y las estimaciones), pero su posición en el mapa sale del camino alternativo.
 * Como el alternativo es más largo, cada metro real que recorre vale menos metros virtuales
 * (`escalaDesvio`): tarda más en pasar de una parada a la otra, que es justo lo que pasa en la calle.
 */
final class RutaSimulada
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $puntos  [longitud, latitud]
     * @param  array<int, float>  $distancias  metros acumulados hasta cada punto
     * @param  array<int, array{orden: int, distancia_m: float, nombre: string}>  $paradas  en orden
     * @param  array<int, array{puntos: array, distancias: array}>  $caminosAlternativos  por orden de la parada de salida
     */
    public function __construct(
        public readonly int $ramalId,
        public readonly int $ramalOpuestoId,
        public readonly array $puntos,
        public readonly array $distancias,
        public readonly array $paradas,
        array $caminosAlternativos = [],
    ) {
        $desvios = [];
        foreach ($caminosAlternativos as $orden => $camino) {
            $salida = $this->parada($orden);
            $llegada = $this->parada($orden + 1);

            if ($salida === null || $llegada === null) {
                continue;
            }

            $desvios[$orden] = [
                'desde_m' => (float) $salida['distancia_m'],
                'hasta_m' => (float) $llegada['distancia_m'],
                'puntos' => $camino['puntos'],
                'distancias' => $camino['distancias'],
                'largo_m' => (float) $camino['distancias'][array_key_last($camino['distancias'])],
            ];
        }

        $this->desvios = $desvios;
    }

    /** @var array<int, array{desde_m: float, hasta_m: float, puntos: array, distancias: array, largo_m: float}> */
    private readonly array $desvios;

    public function largoMetros(): float
    {
        return (float) $this->distancias[array_key_last($this->distancias)];
    }

    /** El desvío que sale de la parada con ese orden, o null si ahí no hay camino alternativo. */
    public function desvio(int $orden): ?array
    {
        return $this->desvios[$orden] ?? null;
    }

    /** Metros del ramal normal que avanza el colectivo por cada metro real que anda por el camino alternativo. */
    public function escalaDesvio(int $orden): float
    {
        $d = $this->desvios[$orden];

        return ($d['hasta_m'] - $d['desde_m']) / $d['largo_m'];
    }

    /**
     * Escala de avance en este punto: la del desvío si el colectivo está recorriéndolo, 1 si va por el recorrido normal.
     */
    public function escalaEn(float $metros, ?int $desvioOrden): float
    {
        $d = $desvioOrden !== null ? ($this->desvios[$desvioOrden] ?? null) : null;

        return $d !== null && $metros >= $d['desde_m'] && $metros < $d['hasta_m'] ? $this->escalaDesvio($desvioOrden) : 1.0;
    }

    /** ¿Está el colectivo andando por el camino alternativo en este punto? */
    public function enDesvio(float $metros, ?int $desvioOrden): bool
    {
        $d = $desvioOrden !== null ? ($this->desvios[$desvioOrden] ?? null) : null;

        return $d !== null && $metros >= $d['desde_m'] && $metros < $d['hasta_m'];
    }

    /** Primera parada que queda por delante de `$ultimaParadaOrden`, o null si ya pasó la última. */
    public function siguienteParada(int $ultimaParadaOrden): ?array
    {
        foreach ($this->paradas as $parada) {
            if ($parada['orden'] > $ultimaParadaOrden) {
                return $parada;
            }
        }

        return null;
    }

    /** La parada con ese orden, o null. */
    public function parada(int $orden): ?array
    {
        foreach ($this->paradas as $parada) {
            if ($parada['orden'] === $orden) {
                return $parada;
            }
        }

        return null;
    }

    /** Última parada en o antes de `$metros` (para ubicar un colectivo recién puesto en el recorrido). */
    public function paradaHasta(float $metros): int
    {
        $orden = 0;
        foreach ($this->paradas as $parada) {
            if ($parada['distancia_m'] <= $metros + 0.5) {
                $orden = $parada['orden'];
            }
        }

        return $orden;
    }

    /**
     * Posición a `$metros` del inicio: [longitud, latitud, rumbo]. El rumbo va en grados, 0 al este
     * y creciendo hacia el sur (el mismo sentido que usa la pantalla). Con `$desvioOrden`, dentro del tramo
     * desviado la posición sale del camino alternativo.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public function punto(float $metros, ?int $desvioOrden = null): array
    {
        $d = $desvioOrden !== null ? ($this->desvios[$desvioOrden] ?? null) : null;

        if ($d !== null && $metros > $d['desde_m'] && $metros < $d['hasta_m']) {
            $real = ($metros - $d['desde_m']) / ($d['hasta_m'] - $d['desde_m']) * $d['largo_m'];

            return self::interpolar($d['puntos'], $d['distancias'], $real);
        }

        return self::interpolar($this->puntos, $this->distancias, max(0.0, min($metros, $this->largoMetros())));
    }

    /**
     * Puntos por los que pasa el colectivo yendo de `$desde` a `$hasta` metros, con ambos extremos.
     * Así el navegador dobla en las esquinas en lugar de cortar camino. Si hay un desvío en curso, el
     * pedazo que cae dentro del tramo desviado sigue el camino alternativo.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function tramo(float $desde, float $hasta, ?int $desvioOrden = null): array
    {
        [$desde, $hasta] = [max(0.0, min($desde, $hasta)), min($this->largoMetros(), max($desde, $hasta))];
        $d = $desvioOrden !== null ? ($this->desvios[$desvioOrden] ?? null) : null;

        $intermedios = [];   // [metros virtuales, punto]

        for ($i = 0; $i < count($this->puntos); $i++) {
            $m = $this->distancias[$i];
            $dentro = $d !== null && $m > $d['desde_m'] && $m < $d['hasta_m'];

            if ($m > $desde && $m < $hasta && ! $dentro) {
                $intermedios[] = [$m, $this->puntos[$i]];
            }
        }

        if ($d !== null) {
            $ancho = $d['hasta_m'] - $d['desde_m'];
            for ($i = 0; $i < count($d['puntos']); $i++) {
                $virtual = $d['desde_m'] + $d['distancias'][$i] / $d['largo_m'] * $ancho;

                if ($virtual > $desde && $virtual < $hasta && $virtual > $d['desde_m'] && $virtual < $d['hasta_m']) {
                    $intermedios[] = [$virtual, $d['puntos'][$i]];
                }
            }
        }

        usort($intermedios, fn ($a, $b) => $a[0] <=> $b[0]);

        $salida = [array_slice($this->punto($desde, $desvioOrden), 0, 2)];
        foreach ($intermedios as [, $punto]) {
            $salida[] = $punto;
        }
        $salida[] = array_slice($this->punto($hasta, $desvioOrden), 0, 2);

        return $salida;
    }

    /**
     * Punto a `$metros` del inicio de una lista de puntos con distancias acumuladas.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function interpolar(array $puntos, array $distancias, float $metros): array
    {
        $i = self::segmento($distancias, $metros);

        [$a, $b] = [$puntos[$i - 1], $puntos[$i]];
        $largo = $distancias[$i] - $distancias[$i - 1];
        $t = $largo > 0 ? ($metros - $distancias[$i - 1]) / $largo : 0.0;

        return [
            $a[0] + ($b[0] - $a[0]) * $t,
            $a[1] + ($b[1] - $a[1]) * $t,
            self::rumbo($a, $b),
        ];
    }

    /** Índice del primer punto que está a `$metros` o más, para tener el segmento [i-1, i]. */
    private static function segmento(array $distancias, float $metros): int
    {
        $bajo = 1;
        $alto = count($distancias) - 1;

        while ($bajo < $alto) {
            $medio = intdiv($bajo + $alto, 2);
            if ($distancias[$medio] < $metros) {
                $bajo = $medio + 1;
            } else {
                $alto = $medio;
            }
        }

        return $bajo;
    }

    /** Rumbo en pantalla (x al este, y al sur) de a hacia b, corrigiendo la longitud por la latitud. */
    private static function rumbo(array $a, array $b): float
    {
        $dx = ($b[0] - $a[0]) * cos(deg2rad(($a[1] + $b[1]) / 2));
        $dy = -($b[1] - $a[1]);

        return rad2deg(atan2($dy, $dx));
    }
}
