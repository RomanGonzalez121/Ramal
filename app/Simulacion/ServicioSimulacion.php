<?php

namespace App\Simulacion;

use App\Events\PosicionesActualizadas;
use App\Historial\Instantanea;
use App\Models\Colectivo;
use App\Models\HistorialPosicion;
use App\Models\Horario;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\Ramal;
use App\Models\Simulacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Une el simulador (PHP puro) con la base de datos y el tiempo real.
 * Cada `tick()` avanza a todos los colectivos, guarda dónde quedaron y avisa a quien los esté mirando.
 */
class ServicioSimulacion
{
    /** @var array<int, RutaSimulada>|null */
    private ?array $rutas = null;

    private ?Simulador $simulador = null;

    private Celdas $celdas;

    /** De dónde vienen los incidentes que se crean: 'simulacion' en vivo, 'relleno' al rehacer el día. */
    public string $origenIncidentes = 'simulacion';

    /** Momento de la última foto del historial (se carga de la base la primera vez). */
    private ?Carbon $ultimaFoto = null;

    private bool $ultimaFotoCargada = false;

    public function __construct()
    {
        $this->celdas = Celdas::desdeConfiguracion();
    }

    /** Pone a los 40 colectivos repartidos por sus recorridos, si todavía no están. */
    public function preparar(): void
    {
        $sim = Simulacion::actual();
        $simulador = $this->simulador($sim->semilla);

        $porLinea = Colectivo::with('linea.ramales')->orderBy('interno')->get()->groupBy('linea_id');
        $ahora = now();

        foreach ($porLinea as $colectivos) {
            foreach ($colectivos->values() as $indice => $colectivo) {
                if (Posicion::where('colectivo_id', $colectivo->id)->exists()) {
                    continue;
                }

                $ida = $colectivo->linea->ramales->firstWhere('sentido', 'ida');
                $estado = $simulador->estadoInicial($colectivo->id, $ida->id, $indice, $colectivos->count());
                $this->guardar($estado, 0, $ahora);
            }
        }
    }

    /**
     * Avanza un tick. Devuelve lo que se emitió, por celda (útil para las pruebas).
     * Con `$emitir = false` no avisa a nadie por tiempo real (al rehacer el día, para no mandar miles de mensajes).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function tick(float $dt, ?Carbon $ahora = null, bool $emitir = true): array
    {
        // Siempre se guarda en UTC, venga la hora en la zona que venga.
        $ahora = ($ahora ?? now())->copy()->utc();
        $sim = Simulacion::actual();
        $simulador = $this->simulador($sim->semilla);
        $tick = $sim->tick + 1;

        $posiciones = Posicion::with('colectivo.linea.ramales')->get();
        $porCelda = [];

        // Lo que los operadores pidieron desde el panel. Solo el simulador mueve a los colectivos: las órdenes se
        // anotan en el incidente y se aplican acá, en el siguiente tick.
        $ordenes = Incidente::query()
            ->whereIn('estado', [Incidente::ACTIVO, Incidente::ATENDIDO])
            ->where(fn ($q) => $q->where('accion_pedida', 'resolver')->orWhere(fn ($q) => $q->where('estado', Incidente::ATENDIDO)->where('ajuste_aplicado', false)))
            ->get()
            ->groupBy('colectivo_id');

        DB::transaction(function () use ($posiciones, $simulador, $tick, $dt, $ahora, $ordenes, &$porCelda) {
            foreach ($posiciones as $posicion) {
                $colectivo = $posicion->colectivo;
                $enServicio = $this->enServicio($colectivo, $ahora);
                $estado = $posicion->aEstado();
                $antes = $posicion->estado;

                if (! $enServicio) {
                    if ($antes !== EstadoColectivo::FUERA_DE_SERVICIO) {
                        $guardada = $this->guardar($estado->con(['estado' => EstadoColectivo::FUERA_DE_SERVICIO, 'velocidadMs' => 0.0]), $tick, $ahora, $posicion);
                        $porCelda[$this->celdaDe($guardada)][] = $this->resumen($colectivo, $guardada, [], true);
                    }

                    continue;
                }

                $salto = false;
                if ($antes === EstadoColectivo::FUERA_DE_SERVICIO) {
                    // Empieza el servicio: sale de la terminal, uno cada tanto, como una salida programada.
                    $ida = $colectivo->linea->ramales->firstWhere('sentido', 'ida');
                    $orden = (int) substr($colectivo->interno, -2) - 1;
                    $frecuencia = $this->frecuencia($colectivo) * 60;
                    $estado = $simulador->estadoEnTerminal($colectivo->id, $ida->id, $orden * $frecuencia);
                    $salto = true;
                }

                $estado = $this->aplicarOrdenes($simulador, $estado, $ordenes->get($colectivo->id, collect()), $ahora);

                $distanciaAntes = $estado->distanciaM;
                $ramalAntes = $estado->ramalId;
                $desvioAntes = $estado->desvioOrden;

                [$nuevo, $eventos] = $simulador->avanzar($estado, $tick, $dt);
                $this->registrarIncidentes($colectivo, $nuevo, $eventos, $ahora);

                $guardada = $this->guardar($nuevo, $tick, $ahora, $posicion, $this->promedioMovil($posicion, $nuevo, $dt));

                $mismoRamal = $nuevo->ramalId === $ramalAntes && ! $salto;
                // Si en este tick estuvo por el camino alternativo (aunque haya vuelto al recorrido al final), el trazado lo sigue.
                $ruta = $mismoRamal
                    ? $this->rutas()[$nuevo->ramalId]->tramo($distanciaAntes, $nuevo->distanciaM, $desvioAntes ?? $nuevo->desvioOrden)
                    : [[$guardada->longitud, $guardada->latitud]];

                $porCelda[$this->celdas->de($guardada->longitud, $guardada->latitud)][] = $this->resumen($colectivo, $guardada, $ruta, ! $mismoRamal);
            }
        });

        $sim->update(['tick' => $tick, 'ultimo_tick_en' => $ahora]);
        $this->guardarFoto($tick, $ahora, $posiciones);

        if ($emitir) {
            foreach ($porCelda as $celda => $colectivos) {
                PosicionesActualizadas::dispatch((string) $celda, $tick, $colectivos);
            }
        }

        return $porCelda;
    }

    /**
     * Guarda una foto del historial si pasaron `ramal.historial.paso_s` segundos desde la anterior (M8).
     * Va por tiempo y no por número de tick, así sirve igual en vivo (ticks de 2 s) que al rehacer el día (ticks de 10 s).
     *
     * @param  Collection<int, Posicion>  $posiciones  ya actualizadas en este tick
     */
    private function guardarFoto(int $tick, Carbon $ahora, $posiciones): void
    {
        $paso = (float) config('ramal.historial.paso_s');

        if (! $this->ultimaFotoCargada) {
            $ultima = HistorialPosicion::max('momento');
            $this->ultimaFoto = $ultima ? Carbon::parse($ultima, 'UTC') : null;
            $this->ultimaFotoCargada = true;
        }

        if ($this->ultimaFoto !== null && abs($ahora->diffInSeconds($this->ultimaFoto)) < $paso - 0.5) {
            return;
        }

        $enCalle = $posiciones
            ->where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)
            ->map(fn (Posicion $p) => [
                'id' => $p->colectivo_id,
                'ramal' => $p->ramal_id,
                'lon' => $p->longitud,
                'lat' => $p->latitud,
                'rumbo' => $p->rumbo,
                'estado' => $p->estado,
            ])
            ->values()
            ->all();

        HistorialPosicion::create([
            'momento' => $ahora,
            'tick' => $tick,
            'cantidad' => count($enCalle),
            'datos' => Instantanea::empaquetar($enCalle),
        ]);

        $this->ultimaFoto = $ahora;
    }

    /** Olvida cuál fue la última foto (por ejemplo, después de borrar el historial). */
    public function reiniciarHistorial(): void
    {
        $this->ultimaFotoCargada = false;
        $this->ultimaFoto = null;
    }

    /** Estado de todos los colectivos que se ven, para quien recién llega al mapa (sin esperar al próximo tick). */
    public function instantanea(?array $vista = null): array
    {
        $celdas = $vista ? $this->celdas->queCruzan($vista) : null;

        return Posicion::with('colectivo.linea')
            ->where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)
            ->get()
            ->filter(fn (Posicion $p) => $celdas === null || in_array($this->celdas->de($p->longitud, $p->latitud), $celdas, true))
            ->map(fn (Posicion $p) => $this->resumen($p->colectivo, $p, [[$p->longitud, $p->latitud]], true))
            ->values()
            ->all();
    }

    public function enServicio(Colectivo $colectivo, Carbon $ahora): bool
    {
        if (config('ramal.siempre_en_servicio')) {
            return true;
        }

        $local = $ahora->copy()->setTimezone(config('ramal.zona_horaria'));
        $dia = match ($local->dayOfWeekIso) {
            6 => 'sabado',
            7 => 'domingo',
            default => 'habil',
        };

        $horario = $colectivo->linea->horarios()->where('dia', $dia)->first();
        if (! $horario) {
            return false;
        }

        $hora = $local->format('H:i:s');

        return $hora >= $horario->desde && $hora <= $horario->hasta;
    }

    /**
     * Velocidad media de los últimos ~60 s, contando solo mientras se mueve (las esperas en parada
     * se suman aparte en la estimación). Es lo que mira M5 para saber qué tan rápido viene de verdad.
     */
    private function promedioMovil(Posicion $posicion, EstadoColectivo $nuevo, float $dt): float
    {
        $previa = $posicion->velocidad_media_ms ?: $nuevo->velocidadCrucero * 0.85;

        if ($nuevo->velocidadMs < 0.3) {
            return $previa;
        }

        // En un desvío cada metro real avanza menos metros del recorrido: la estimación trabaja con metros del recorrido.
        $efectiva = $nuevo->velocidadMs * $this->rutas()[$nuevo->ramalId]->escalaEn($nuevo->distanciaM, $nuevo->desvioOrden);

        return $previa + ($efectiva - $previa) * (1 - exp(-$dt / 60));
    }

    /**
     * Aplica al colectivo lo que un operador pidió sobre su incidente: atenderlo o darlo por resuelto.
     *
     * @param  Collection<int, Incidente>  $pedidos
     */
    private function aplicarOrdenes(Simulador $simulador, EstadoColectivo $estado, $pedidos, Carbon $ahora): EstadoColectivo
    {
        foreach ($pedidos as $incidente) {
            if ($incidente->accion_pedida === 'resolver') {
                [$estado, $resuelto] = $simulador->aplicarOrden($estado, 'resolver');

                $incidente->update($resuelto
                    ? ['estado' => Incidente::RESUELTO, 'fin_en' => $ahora, 'resuelto_por' => 'operador', 'accion_pedida' => null]
                    : ['accion_pedida' => null]);

                continue;
            }

            [$estado] = $simulador->aplicarOrden($estado, 'atender');
            $incidente->update(['ajuste_aplicado' => true]);
        }

        return $estado;
    }

    private function frecuencia(Colectivo $colectivo): int
    {
        return Horario::where('linea_id', $colectivo->linea_id)->where('dia', 'habil')->value('frecuencia_min') ?? 12;
    }

    private function celdaDe(Posicion $p): string
    {
        return $this->celdas->de($p->longitud, $p->latitud);
    }

    private function guardar(EstadoColectivo $e, int $tick, Carbon $ahora, ?Posicion $existente = null, ?float $velocidadMedia = null): Posicion
    {
        [$longitud, $latitud, $rumbo] = $this->rutas()[$e->ramalId]->punto($e->distanciaM, $e->desvioOrden);

        $datos = [
            'desvio_orden' => $e->desvioOrden,
            'ramal_id' => $e->ramalId,
            'distancia_m' => $e->distanciaM,
            'velocidad_ms' => $e->velocidadMs,
            'velocidad_media_ms' => $velocidadMedia ?? ($existente?->velocidad_media_ms ?: $e->velocidadCrucero * 0.85),
            'estado' => $e->estado,
            'espera_s' => $e->esperaS,
            'ultima_parada' => $e->ultimaParada,
            'velocidad_crucero' => $e->velocidadCrucero,
            'incidente_tipo' => $e->incidente,
            'incidente_restante_s' => $e->incidenteRestanteS,
            'latitud' => round($latitud, 6),
            'longitud' => round($longitud, 6),
            'rumbo' => (int) round($rumbo),
            'tick' => $tick,
            'actualizado_en' => $ahora,
        ];

        if ($existente) {
            $existente->update($datos);

            return $existente;
        }

        return Posicion::create(['colectivo_id' => $e->colectivoId] + $datos);
    }

    /** @param array<int, array{tipo: string, incidente: string, duracion_s?: float}> $eventos */
    private function registrarIncidentes(Colectivo $colectivo, EstadoColectivo $nuevo, array $eventos, Carbon $ahora): void
    {
        foreach ($eventos as $evento) {
            if ($evento['tipo'] === 'nuevo') {
                Incidente::create([
                    'colectivo_id' => $colectivo->id,
                    'tipo' => $evento['incidente'],
                    'duracion_prevista_s' => (int) round($evento['duracion_s']),
                    'desvio_orden' => $evento['incidente'] === 'desvio' ? $nuevo->desvioOrden : null,
                    'origen' => $this->origenIncidentes,
                    'inicio_en' => $ahora,
                ]);
            } else {
                // Se resolvió solo: pasó el tiempo, o el colectivo volvió al recorrido después de un desvío.
                Incidente::where('colectivo_id', $colectivo->id)
                    ->whereIn('estado', [Incidente::ACTIVO, Incidente::ATENDIDO])
                    ->update(['estado' => Incidente::RESUELTO, 'fin_en' => $ahora, 'resuelto_por' => 'simulacion', 'accion_pedida' => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function resumen(Colectivo $colectivo, Posicion $p, array $ruta, bool $salto): array
    {
        return [
            'id' => $colectivo->id,
            'interno' => $colectivo->interno,
            'linea' => $colectivo->linea->numero,
            'ramal' => $p->ramal_id,
            'estado' => $p->estado,
            'rumbo' => $p->rumbo,
            'velocidad' => round($p->velocidad_ms, 1),
            'salto' => $salto,
            'ruta' => array_map(fn (array $punto) => [round($punto[0], 6), round($punto[1], 6)], $ruta),
        ];
    }

    private function simulador(int $semilla): Simulador
    {
        return $this->simulador ??= new Simulador($semilla, $this->rutas());
    }

    /** @return array<int, RutaSimulada> */
    public function rutas(): array
    {
        if ($this->rutas !== null) {
            return $this->rutas;
        }

        // Cada ramal sabe cuál es el de la otra mano de su línea: ahí sigue cuando llega a la terminal.
        $opuestos = [];
        foreach (Ramal::query()->get()->groupBy('linea_id') as $par) {
            foreach ($par as $ramal) {
                $opuestos[$ramal->id] = $par->firstWhere('id', '!=', $ramal->id)->id;
            }
        }

        $this->rutas = [];
        foreach (Ramal::with(['recorrido', 'paradas', 'desvios'])->get() as $ramal) {
            $this->rutas[$ramal->id] = new RutaSimulada(
                ramalId: $ramal->id,
                ramalOpuestoId: $opuestos[$ramal->id],
                puntos: $ramal->recorrido->puntos,
                distancias: $ramal->recorrido->distancias,
                paradas: $ramal->paradas->map(fn ($p) => [
                    'orden' => $p->pivot->orden,
                    'distancia_m' => (float) $p->pivot->distancia_m,
                    'nombre' => $p->nombre,
                ])->all(),
                caminosAlternativos: $ramal->desvios->mapWithKeys(fn ($d) => [
                    $d->desde_orden => ['puntos' => $d->puntos, 'distancias' => $d->distancias],
                ])->all(),
            );
        }

        return $this->rutas;
    }
}
