<?php

namespace App\Historial;

use InvalidArgumentException;

/**
 * El formato compacto con el que se guarda cada foto del historial (M8).
 *
 * Cada colectivo ocupa 15 bytes, little-endian:
 *
 *   2  id del colectivo (u16)
 *   2  id del ramal (u16)
 *   4  longitud en millonésimas de grado (i32), unos 10 cm de precisión
 *   4  latitud en millonésimas de grado (i32)
 *   2  rumbo en grados (i16)
 *   1  estado (u8, ver ESTADOS)
 *
 * 40 colectivos son 600 bytes por foto; con una foto cada 10 s, unos 5 MB por día. PHP puro, sin base de datos.
 */
final class Instantanea
{
    public const BYTES_POR_COLECTIVO = 15;

    /** El número que se guarda para cada estado. El orden no se puede cambiar sin migrar el historial. */
    public const ESTADOS = [
        'circulando',
        'en_parada',
        'en_terminal',
        'demorado',
        'averiado',
        'fuera_de_recorrido',
        'fuera_de_servicio',
    ];

    private const ESCALA = 1_000_000;

    /**
     * @param  array<int, array{id: int, ramal: int, lon: float, lat: float, rumbo: int, estado: string}>  $colectivos
     */
    public static function empaquetar(array $colectivos): string
    {
        $bytes = '';

        foreach ($colectivos as $c) {
            $estado = array_search($c['estado'], self::ESTADOS, true);

            if ($estado === false) {
                throw new InvalidArgumentException("Estado desconocido: {$c['estado']}");
            }

            $bytes .= pack(
                'vvVVvC',
                $c['id'],
                $c['ramal'],
                self::aSinSigno((int) round($c['lon'] * self::ESCALA)),
                self::aSinSigno((int) round($c['lat'] * self::ESCALA)),
                $c['rumbo'] & 0xFFFF,
                $estado,
            );
        }

        return $bytes;
    }

    /**
     * @return array<int, array{id: int, ramal: int, lon: float, lat: float, rumbo: int, estado: string}>
     */
    public static function desempaquetar(string $bytes): array
    {
        if (strlen($bytes) % self::BYTES_POR_COLECTIVO !== 0) {
            throw new InvalidArgumentException('El bloque no tiene un largo válido.');
        }

        $colectivos = [];

        foreach (str_split($bytes, self::BYTES_POR_COLECTIVO) as $trozo) {
            $c = unpack('vid/vramal/Vlon/Vlat/vrumbo/Cestado', $trozo);

            $colectivos[] = [
                'id' => $c['id'],
                'ramal' => $c['ramal'],
                'lon' => self::aConSigno($c['lon']) / (float) self::ESCALA,
                'lat' => self::aConSigno($c['lat']) / (float) self::ESCALA,
                'rumbo' => self::aConSigno($c['rumbo'], 16),
                'estado' => self::ESTADOS[$c['estado']] ?? throw new InvalidArgumentException("Estado inválido: {$c['estado']}"),
            ];
        }

        return $colectivos;
    }

    /** Pasa un entero con signo de 32 bits a su versión sin signo, para guardarlo con el formato 'V'. */
    private static function aSinSigno(int $n): int
    {
        return $n & 0xFFFFFFFF;
    }

    private static function aConSigno(int $n, int $bits = 32): int
    {
        $tope = 1 << ($bits - 1);

        return $n >= $tope ? $n - ($tope << 1) : $n;
    }
}
