<?php

namespace App\Simulacion;

/**
 * Azar sin estado: el mismo (semilla, colectivo, instante, índice) da siempre el mismo número.
 *
 * Por eso la simulación es reproducible y se puede retomar en cualquier tick, aunque cada tick
 * corra en un proceso distinto: no hay generador que guardar.
 */
final class Azar
{
    /** Número en [0, 1). Usa el mezclador de bits "splitmix" sobre los cuatro enteros. */
    public static function flotante(int $semilla, int $colectivo, int $tick, int $indice = 0): float
    {
        $x = self::mezclar($semilla);
        $x = self::mezclar($x ^ self::multiplicar($colectivo, 0x9E3779B1));
        $x = self::mezclar($x ^ self::multiplicar($tick, 0x85EBCA6B));
        $x = self::mezclar($x ^ self::multiplicar($indice, 0xC2B2AE35));

        return $x / 4294967296;
    }

    /** Número en [$min, $max). */
    public static function entre(int $semilla, int $colectivo, int $tick, int $indice, float $min, float $max): float
    {
        return $min + self::flotante($semilla, $colectivo, $tick, $indice) * ($max - $min);
    }

    private static function mezclar(int $x): int
    {
        $x = ($x + 0x9E3779B9) & 0xFFFFFFFF;
        $x = self::multiplicar($x ^ ($x >> 16), 0x85EBCA6B);
        $x = self::multiplicar($x ^ ($x >> 13), 0xC2B2AE35);

        return ($x ^ ($x >> 16)) & 0xFFFFFFFF;
    }

    /** Producto de dos enteros de 32 bits que no se pasa de 64 bits: se parte en mitades de 16. */
    private static function multiplicar(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        return (($a & 0xFFFF) * $b + ((($a >> 16) * $b & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }
}
