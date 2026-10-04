<?php

namespace App\Simulacion;

/**
 * Divide la ciudad en una grilla. Cada celda tiene su propio canal de tiempo real:
 * el navegador se suscribe solo a las celdas que se ven en el mapa, así el servidor
 * no manda posiciones que nadie está mirando.
 */
final class Celdas
{
    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $caja  [oeste, sur, este, norte]
     */
    public function __construct(
        private readonly array $caja,
        private readonly int $columnas,
        private readonly int $filas,
    ) {}

    public static function desdeConfiguracion(): self
    {
        $c = config('ramal.ciudad');

        return new self($c['caja'], $c['columnas'], $c['filas']);
    }

    /** Identificador de la celda donde cae un punto ("2.1" = columna 2, fila 1). Fuera de la caja se pega al borde. */
    public function de(float $longitud, float $latitud): string
    {
        return $this->columna($longitud).'.'.$this->fila($latitud);
    }

    /**
     * Celdas que toca un rectángulo (lo que se ve en pantalla).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $vista  [oeste, sur, este, norte]
     * @return array<int, string>
     */
    public function queCruzan(array $vista): array
    {
        [$oeste, $sur, $este, $norte] = $vista;
        $celdas = [];

        for ($c = $this->columna($oeste); $c <= $this->columna($este); $c++) {
            for ($f = $this->fila($norte); $f <= $this->fila($sur); $f++) {
                $celdas[] = "{$c}.{$f}";
            }
        }

        return $celdas;
    }

    /** @return array<int, string> */
    public function todas(): array
    {
        $celdas = [];
        for ($c = 0; $c < $this->columnas; $c++) {
            for ($f = 0; $f < $this->filas; $f++) {
                $celdas[] = "{$c}.{$f}";
            }
        }

        return $celdas;
    }

    public function configuracion(): array
    {
        return ['caja' => $this->caja, 'columnas' => $this->columnas, 'filas' => $this->filas];
    }

    private function columna(float $longitud): int
    {
        $t = ($longitud - $this->caja[0]) / ($this->caja[2] - $this->caja[0]);

        return max(0, min($this->columnas - 1, (int) floor($t * $this->columnas)));
    }

    /** Fila 0 es la de más al norte, como se dibuja en pantalla. */
    private function fila(float $latitud): int
    {
        $t = ($this->caja[3] - $latitud) / ($this->caja[3] - $this->caja[1]);

        return max(0, min($this->filas - 1, (int) floor($t * $this->filas)));
    }
}
