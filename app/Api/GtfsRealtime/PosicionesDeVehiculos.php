<?php

namespace App\Api\GtfsRealtime;

use App\Models\Posicion;
use App\Simulacion\EstadoColectivo;
use Illuminate\Support\Carbon;

/**
 * El feed de posiciones de vehículos en el formato GTFS Realtime (https://gtfs.org/realtime/): lo que leen Google Maps,
 * Transit y casi cualquier aplicación de transporte.
 *
 * Se arma primero como un arreglo con los nombres de la especificación (`comoArreglo`, que además sirve para verlo como
 * JSON) y de ahí se escribe en protobuf (`codificar`). Un solo origen de datos, dos formatos.
 *
 * Límite conocido: no hay un feed estático (GTFS) que acompañe a este. `route_id` es el número de la línea y
 * `direction_id` es 0 para la ida y 1 para la vuelta; los viajes no tienen identificador.
 */
final class PosicionesDeVehiculos
{
    public const VERSION = '2.0';

    /** Valores de `VehicleStopStatus` de la especificación. */
    public const STOPPED_AT = 1;

    public const IN_TRANSIT_TO = 2;

    /**
     * @param  iterable<Posicion>  $posiciones  con `colectivo.linea` y `ramal.paradas` cargados
     * @return array{header: array<string, mixed>, entity: array<int, array<string, mixed>>}
     */
    public function comoArreglo(iterable $posiciones, Carbon $ahora): array
    {
        $entidades = [];

        foreach ($posiciones as $p) {
            if ($p->estado === EstadoColectivo::FUERA_DE_SERVICIO) {
                continue;
            }

            [$parada, $secuencia, $estadoParada] = $this->parada($p);

            $vehiculo = [
                'trip' => [
                    'route_id' => (string) $p->colectivo->linea->numero,
                    'direction_id' => $p->ramal->sentido === 'ida' ? 0 : 1,
                ],
                'position' => [
                    'latitude' => round($p->latitud, 6),
                    'longitude' => round($p->longitud, 6),
                    'bearing' => (float) $p->rumbo,
                    'speed' => round($p->velocidad_ms, 2),
                ],
                'current_status' => $estadoParada,
                'timestamp' => ($p->actualizado_en ?? $ahora)->getTimestamp(),
                'vehicle' => [
                    'id' => (string) $p->colectivo->id,
                    'label' => (string) $p->colectivo->interno,
                ],
            ];

            if ($parada !== null) {
                $vehiculo['stop_id'] = (string) $parada;
                $vehiculo['current_stop_sequence'] = $secuencia;
            }

            $entidades[] = ['id' => (string) $p->colectivo->id, 'vehicle' => $vehiculo];
        }

        usort($entidades, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);

        return [
            'header' => [
                'gtfs_realtime_version' => self::VERSION,
                'incrementality' => 'FULL_DATASET',
                'timestamp' => $ahora->getTimestamp(),
            ],
            'entity' => $entidades,
        ];
    }

    /** El mensaje `FeedMessage` en protobuf. */
    public function codificar(array $feed): string
    {
        $cabecera = Protobuf::texto(1, $feed['header']['gtfs_realtime_version'])
            .Protobuf::entero(2, 0) // FULL_DATASET: cada feed trae todos los vehículos
            .Protobuf::entero(3, $feed['header']['timestamp']);

        $salida = Protobuf::mensaje(1, $cabecera);

        foreach ($feed['entity'] as $entidad) {
            $salida .= Protobuf::mensaje(2, Protobuf::texto(1, $entidad['id']).Protobuf::mensaje(4, $this->vehiculo($entidad['vehicle'])));
        }

        return $salida;
    }

    private function vehiculo(array $v): string
    {
        $viaje = Protobuf::texto(5, $v['trip']['route_id']).Protobuf::entero(6, $v['trip']['direction_id']);

        $posicion = Protobuf::decimal32(1, $v['position']['latitude'])
            .Protobuf::decimal32(2, $v['position']['longitude'])
            .Protobuf::decimal32(3, $v['position']['bearing'])
            .Protobuf::decimal32(5, $v['position']['speed']);

        $salida = Protobuf::mensaje(1, $viaje).Protobuf::mensaje(2, $posicion);

        if (isset($v['current_stop_sequence'])) {
            $salida .= Protobuf::entero(3, $v['current_stop_sequence']);
        }

        $salida .= Protobuf::entero(4, $v['current_status']);
        $salida .= Protobuf::entero(5, $v['timestamp']);

        if (isset($v['stop_id'])) {
            $salida .= Protobuf::texto(7, $v['stop_id']);
        }

        return $salida.Protobuf::mensaje(8, Protobuf::texto(1, $v['vehicle']['id']).Protobuf::texto(2, $v['vehicle']['label']));
    }

    /**
     * La parada a la que se refiere el estado del vehículo: en la que está detenido o la próxima a la que se dirige.
     *
     * @return array{0: ?int, 1: ?int, 2: int} id de parada, número de orden y estado
     */
    private function parada(Posicion $p): array
    {
        $paradas = $p->ramal->paradas;
        $detenido = in_array($p->estado, [EstadoColectivo::EN_PARADA, EstadoColectivo::EN_TERMINAL], true);

        if ($detenido) {
            $actual = $paradas->first(fn ($x) => $x->pivot->orden === $p->ultima_parada);

            return [$actual?->id, $actual?->pivot->orden, self::STOPPED_AT];
        }

        $proxima = $paradas->first(fn ($x) => $x->pivot->orden > $p->ultima_parada);

        return [$proxima?->id, $proxima?->pivot->orden, self::IN_TRANSIT_TO];
    }
}
