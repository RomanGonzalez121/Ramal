<?php

namespace App\Estimaciones;

/**
 * Cuándo se espera que un colectivo llegue a una parada.
 */
final class Llegada
{
    public function __construct(
        public readonly int $colectivoId,
        public readonly float $segundos,
        /** Verdadero si hay algo que hace poco confiable la cuenta (por ejemplo, una falla sin hora de arreglo). */
        public readonly bool $incierta = false,
    ) {}
}
