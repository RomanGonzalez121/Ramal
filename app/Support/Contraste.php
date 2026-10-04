<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Contraste según WCAG 2.x: (L1 + 0,05) / (L2 + 0,05) con L = luminancia relativa.
 */
class Contraste
{
    public static function luminancia(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (int $canal) => self::linealizar($canal / 255),
            self::canales($hex),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Texto para mostrar, con coma decimal: "4,9:1". */
    public static function formato(string $a, string $b): string
    {
        return number_format(self::ratio($a, $b), 1, ',', '').':1';
    }

    /** @return array{int,int,int} */
    private static function canales(string $hex): array
    {
        $limpio = ltrim($hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $limpio)) {
            throw new InvalidArgumentException("Color inválido: {$hex}");
        }

        return [
            hexdec(substr($limpio, 0, 2)),
            hexdec(substr($limpio, 2, 2)),
            hexdec(substr($limpio, 4, 2)),
        ];
    }

    private static function linealizar(float $c): float
    {
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
}
