<?php

namespace App\Estimaciones;

/**
 * Lo que se sabe de un colectivo en este momento, como lo vería un sistema real de seguimiento:
 * dónde está, hacia dónde va, cuánto va a esperar y a qué velocidad anduvo en el último minuto.
 */
final class Seguimiento
{
    public function __construct(
        public readonly int $colectivoId,
        public readonly int $ramalId,
        public readonly float $distanciaM,
        public readonly float $esperaS,
        public readonly string $estado,
        public readonly float $velocidadMediaMs,
        public readonly ?string $incidente = null,
        /** Orden de la parada de la que sale el desvío en curso, o null. */
        public readonly ?int $desvioOrden = null,
    ) {}
}
