<?php

namespace Database\Seeders;

use App\Models\Chofer;
use App\Models\Colectivo;
use App\Models\Horario;
use App\Models\Linea;
use App\Models\Parada;
use App\Models\Ramal;
use App\Support\Geo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga la ciudad: 5 líneas ficticias sobre calles reales de Paraná, con sus paradas,
 * recorridos (ya calculados, ver scripts/calcular-recorridos.mjs), choferes y horarios inventados.
 */
class CiudadSeeder extends Seeder
{
    private const COLECTIVOS_POR_LINEA = 8;

    public function run(): void
    {
        $definicion = $this->leer('lineas.json');
        $recorridos = $this->leer('recorridos.json');

        DB::transaction(function () use ($definicion, $recorridos) {
            // Las coordenadas de lugares son el centro del edificio o del parque; la parada va sobre la calle.
            $paradas = collect($definicion['paradas'])->map(
                fn (array $p, string $clave) => Parada::create(array_merge($p, $recorridos['paradas'][$clave] ?? [])),
            );
            $legajo = 1000;

            foreach ($definicion['lineas'] as $datos) {
                $claves = $datos['ida'];
                $nombres = [
                    'ida' => $paradas[end($claves)]->nombre,
                    'vuelta' => $paradas[$claves[0]]->nombre,
                ];

                $linea = Linea::create([
                    'numero' => $datos['numero'],
                    'nombre' => $datos['nombre'],
                    'destino' => $nombres['ida'],
                ]);

                foreach (['ida' => $claves, 'vuelta' => array_reverse($claves)] as $sentido => $orden) {
                    $ramal = Ramal::create([
                        'linea_id' => $linea->id,
                        'sentido' => $sentido,
                        'destino' => $nombres[$sentido],
                    ]);

                    $this->cargarRecorrido($ramal, $recorridos, "{$linea->numero}-{$sentido}", $orden, $paradas);
                }

                for ($n = 1; $n <= self::COLECTIVOS_POR_LINEA; $n++) {
                    $chofer = Chofer::create([
                        'nombre' => fake('es_AR')->name(),
                        'legajo' => (string) ++$legajo,
                    ]);

                    Colectivo::create([
                        'linea_id' => $linea->id,
                        'chofer_id' => $chofer->id,
                        'interno' => $linea->numero.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                    ]);
                }

                foreach ([
                    ['habil', '05:30', '23:30', 12],
                    ['sabado', '06:00', '23:00', 18],
                    ['domingo', '07:00', '22:30', 25],
                ] as [$dia, $desde, $hasta, $frecuencia]) {
                    Horario::create([
                        'linea_id' => $linea->id,
                        'dia' => $dia,
                        'desde' => $desde,
                        'hasta' => $hasta,
                        'frecuencia_min' => $frecuencia,
                    ]);
                }
            }
        });

        // Los caminos alternativos para los desvíos (si están calculados).
        $this->call(DesviosSeeder::class);
    }

    private function cargarRecorrido(Ramal $ramal, array $recorridos, string $claveRamal, array $orden, $paradas): void
    {
        $datos = $recorridos['ramales'][$claveRamal] ?? throw new RuntimeException("Falta el recorrido {$claveRamal}. Corré scripts/calcular-recorridos.mjs.");

        $puntos = $datos['puntos'];
        $acumuladas = Geo::distanciasAcumuladas($puntos);

        $ramal->recorrido()->create([
            'puntos' => $puntos,
            'distancias' => array_map(fn (float $m) => round($m, 1), $acumuladas),
            'largo_m' => (int) round(end($acumuladas)),
            'fuente' => $recorridos['fuente'],
            'calculado_el' => $recorridos['calculado'],
        ]);

        // Cada parada cae a cierta distancia del inicio; se busca siempre de la anterior en adelante.
        $desde = 0.0;
        foreach ($orden as $posicion => $clave) {
            $parada = $paradas[$clave];
            $proyeccion = Geo::proyectar([$parada->longitud, $parada->latitud], $puntos, $acumuladas, $desde);
            $desde = $proyeccion['a_lo_largo_m'];

            $ramal->paradas()->attach($parada->id, [
                'orden' => $posicion + 1,
                'distancia_m' => (int) round($desde),
            ]);
        }
    }

    private function leer(string $archivo): array
    {
        $ruta = database_path("datos/{$archivo}");

        if (! is_file($ruta)) {
            throw new RuntimeException("No existe {$ruta}");
        }

        return json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
    }
}
