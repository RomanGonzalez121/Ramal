<?php

namespace App\Operacion;

use App\Models\Incidente;
use App\Models\Linea;
use App\Models\Posicion;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use Illuminate\Support\Carbon;

/**
 * Lo que ve el operador (M6): cuántos colectivos andan bien, cuáles necesitan atención,
 * los incidentes del día y los números para los gráficos.
 *
 * "Del día" es desde la medianoche de Paraná, no desde la medianoche del servidor.
 */
class Resumen
{
    /** Cuántos incidentes se listan. */
    public const LISTADO = 25;

    public function __construct(private readonly ServicioSimulacion $simulacion) {}

    /** @return array<string, mixed> */
    public function calcular(?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $zona = config('ramal.zona_horaria');
        $local = $ahora->copy()->setTimezone($zona);
        $inicioDia = $local->copy()->startOfDay()->setTimezone('UTC');

        $posiciones = Posicion::with('colectivo.linea')
            ->where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)
            ->get();

        $incidentesDelDia = Incidente::with('colectivo.linea', 'atendidoPor')
            ->where('inicio_en', '>=', $inicioDia)
            ->get();

        return [
            'generado_en' => $ahora->toIso8601String(),
            'indicadores' => $this->indicadores($posiciones, $incidentesDelDia),
            'atencion' => $this->atencion($posiciones, $ahora),
            'incidentes' => $this->listado($incidentesDelDia, $ahora, $zona),
            'graficos' => [
                'por_hora' => $this->porHora($incidentesDelDia, $zona),
                'por_linea' => $this->porLinea($incidentesDelDia),
                'por_tipo' => $this->porTipo($incidentesDelDia, $ahora),
            ],
            'hora_actual' => (int) $local->format('G'),
        ];
    }

    /** @return array<string, int|float> */
    private function indicadores($posiciones, $incidentes): array
    {
        $enServicio = $posiciones->count();
        $conProblema = $posiciones->whereIn('estado', [EstadoColectivo::DEMORADO, EstadoColectivo::AVERIADO, 'fuera_de_recorrido'])->count();

        return [
            'en_servicio' => $enServicio,
            'demorados' => $posiciones->where('estado', EstadoColectivo::DEMORADO)->count(),
            'averiados' => $posiciones->where('estado', EstadoColectivo::AVERIADO)->count(),
            'fuera_de_recorrido' => $posiciones->where('estado', 'fuera_de_recorrido')->count(),
            'incidentes_activos' => $incidentes->where('estado', '!=', 'resuelto')->count(),
            'incidentes_del_dia' => $incidentes->count(),
            // Porcentaje de colectivos que están andando sin problemas.
            'en_hora_pct' => $enServicio > 0 ? (int) round(($enServicio - $conProblema) / $enServicio * 100) : 100,
        ];
    }

    /**
     * Los colectivos que necesitan que alguien los mire, con dónde están y desde cuándo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function atencion($posiciones, Carbon $ahora): array
    {
        $rutas = $this->simulacion->rutas();

        return $posiciones
            ->whereIn('estado', [EstadoColectivo::DEMORADO, EstadoColectivo::AVERIADO, 'fuera_de_recorrido'])
            ->map(function (Posicion $p) use ($rutas, $ahora) {
                $incidente = Incidente::with('atendidoPor')->where('colectivo_id', $p->colectivo_id)->where('estado', '!=', 'resuelto')->latest('inicio_en')->first();

                return [
                    'interno' => $p->colectivo->interno,
                    'linea' => $p->colectivo->linea->numero,
                    'estado' => $p->estado,
                    'tipo' => $incidente?->tipo,
                    'minutos' => $incidente ? (int) round(abs($incidente->inicio_en->diffInSeconds($ahora)) / 60) : null,
                    'ubicacion' => $this->ubicacion($rutas[$p->ramal_id], $p->ultima_parada),
                    // Lo que el operador puede hacer con este colectivo, sin ir a buscar el incidente a otra lista.
                    'incidente' => $incidente ? [
                        'id' => $incidente->id,
                        'estado' => $incidente->estado,
                        'atendido_por' => $incidente->atendidoPor?->name,
                        'puede_atender' => $incidente->estado === Incidente::ACTIVO,
                        'puede_resolver' => $incidente->puedeResolverseAMano(),
                        'resolviendo' => $incidente->accion_pedida !== null,
                    ] : null,
                ];
            })
            ->sortByDesc('minutos')
            ->values()
            ->all();
    }

    /** "Entre Plaza y Terminal", a partir de la última parada que pasó. */
    private function ubicacion($ruta, int $ultimaParada): string
    {
        $paradas = collect($ruta->paradas);
        $anterior = $paradas->firstWhere('orden', $ultimaParada);
        $siguiente = $paradas->firstWhere('orden', $ultimaParada + 1);

        return match (true) {
            $anterior && $siguiente => "Entre {$anterior['nombre']} y {$siguiente['nombre']}",
            (bool) $anterior => "En {$anterior['nombre']}",
            default => 'En el recorrido',
        };
    }

    /**
     * Los últimos incidentes: primero los que siguen abiertos, después los más recientes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function listado($incidentes, Carbon $ahora, string $zona): array
    {
        return $incidentes
            ->sortBy([fn ($a, $b) => ($a->estado === 'resuelto') <=> ($b->estado === 'resuelto'), fn ($a, $b) => $b->inicio_en <=> $a->inicio_en])
            ->take(self::LISTADO)
            ->map(fn (Incidente $i) => [
                'id' => $i->id,
                'hora' => $i->inicio_en->copy()->setTimezone($zona)->format('H:i'),
                'tipo' => $i->tipo,
                'estado' => $i->estado,
                'interno' => $i->colectivo->interno,
                'linea' => $i->colectivo->linea->numero,
                'duracion_min' => (int) round(abs($i->inicio_en->diffInSeconds($i->fin_en ?? $ahora)) / 60),
                'atendido_por' => $i->atendidoPor?->name,
                'resuelto_por' => $i->resuelto_por,
                // Qué botones se muestran: se atiende lo que nadie tomó; se resuelve a mano todo menos un desvío.
                'puede_atender' => $i->estado === Incidente::ACTIVO,
                'puede_resolver' => $i->puedeResolverseAMano(),
                'resolviendo' => $i->accion_pedida !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * Cuántos incidentes empezaron en cada hora del día (0 a 23), hora de Paraná.
     *
     * @return array<int, int>
     */
    private function porHora($incidentes, string $zona): array
    {
        $horas = array_fill(0, 24, 0);

        foreach ($incidentes as $i) {
            $horas[(int) $i->inicio_en->copy()->setTimezone($zona)->format('G')]++;
        }

        return $horas;
    }

    /** @return array<int, array{linea: int, cantidad: int}> las cinco líneas, aunque no tengan incidentes */
    private function porLinea($incidentes): array
    {
        $cuentas = $incidentes->groupBy(fn ($i) => $i->colectivo->linea->numero)->map->count();

        return Linea::orderBy('numero')->pluck('numero')
            ->map(fn ($n) => ['linea' => $n, 'cantidad' => (int) ($cuentas[$n] ?? 0)])
            ->all();
    }

    /** @return array<int, array{tipo: string, cantidad: int, minutos_promedio: int}> */
    private function porTipo($incidentes, Carbon $ahora): array
    {
        return collect(['demora', 'desvio', 'falla'])->map(function (string $tipo) use ($incidentes, $ahora) {
            $deEsteTipo = $incidentes->where('tipo', $tipo);
            $promedio = $deEsteTipo->isEmpty() ? 0 : $deEsteTipo->avg(fn ($i) => abs($i->inicio_en->diffInSeconds($i->fin_en ?? $ahora)) / 60);

            return ['tipo' => $tipo, 'cantidad' => $deEsteTipo->count(), 'minutos_promedio' => (int) round($promedio)];
        })->all();
    }
}
