<?php

namespace App\Api\GtfsRealtime;

/**
 * Un escritor mínimo del formato Protocol Buffers (el que usa GTFS Realtime), hecho a mano: sin biblioteca.
 *
 * Un mensaje es una cola de campos. Cada campo empieza con una etiqueta (número del campo y tipo de dato) y sigue con el
 * valor. Con cuatro tipos alcanza para el feed de posiciones: números enteros de largo variable (varint), decimales de
 * 32 bits, decimales de 64 bits y bloques con largo (texto o mensajes anidados).
 */
final class Protobuf
{
    public const VARINT = 0;

    public const DE_64_BITS = 1;

    public const CON_LARGO = 2;

    public const DE_32_BITS = 5;

    /** Un entero sin signo en varint: 7 bits por byte, el bit alto avisa si sigue otro byte. */
    public static function varint(int $valor): string
    {
        if ($valor < 0) {
            throw new \InvalidArgumentException('Este escritor solo codifica enteros sin signo.');
        }

        $bytes = '';
        while ($valor > 0x7F) {
            $bytes .= chr(($valor & 0x7F) | 0x80);
            $valor >>= 7;
        }

        return $bytes.chr($valor);
    }

    private static function etiqueta(int $campo, int $tipo): string
    {
        return self::varint(($campo << 3) | $tipo);
    }

    public static function entero(int $campo, int $valor): string
    {
        return self::etiqueta($campo, self::VARINT).self::varint($valor);
    }

    /** Un decimal de 32 bits (float), en el orden de bytes "little endian" que pide el formato. */
    public static function decimal32(int $campo, float $valor): string
    {
        return self::etiqueta($campo, self::DE_32_BITS).pack('g', $valor);
    }

    public static function decimal64(int $campo, float $valor): string
    {
        return self::etiqueta($campo, self::DE_64_BITS).pack('e', $valor);
    }

    public static function texto(int $campo, string $valor): string
    {
        return self::etiqueta($campo, self::CON_LARGO).self::varint(strlen($valor)).$valor;
    }

    /** Un mensaje anidado: sus campos ya codificados, precedidos por el largo. */
    public static function mensaje(int $campo, string $contenido): string
    {
        return self::texto($campo, $contenido);
    }
}
