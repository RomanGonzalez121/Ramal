<?php

namespace App\Estimaciones;

use App\Models\Horario;
use App\Models\Parada;
use App\Models\Posicion;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use Illuminate\Support\Carbon;

/**
 * Arma, para una parada, la lista de próximos colectivos con su cuenta regresiva.
 * Junta lo que guarda la base (dónde está cada colectivo) con el `Estimador` (PHP puro).
 */
class ServicioLlegadas
{
    /** Cuántos colectivos próximos se muestran por cada ramal que pasa por la parada. */
    public const POR_RAMAL = 2;

    public function __construct(private readonly ServicioSimulacion $simulacion) {}

    /**
     * @return array{
     *     parada: array{id: int, nombre: string},
     *     calculado_en: string,
     *     servicio: string,
     *     proximo_servicio: ?string,
     *     llegadas: array<int, array<string, mixed>>
     * }
     */
    public function paraParada(Parada $parada, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $estimador = new Estimador($this->simulacion->rutas());

        $ramales = $parada->ramales()->with('linea')->get();
        $lineaIds = $ramales->pluck('linea_id')->unique();

        $posiciones = Posicion::query()
            ->with('colectivo.linea')
            ->whereHas('colectivo', fn ($q) => $q->whereIn('linea_id', $lineaIds))
            ->where('estado', '!=', EstadoColectivo::FUERA_DE_SERVICIO)
            ->get();

        $llegadas = [];

        $rutas = $this->simulacion->rutas();

        foreach ($ramales as $ramal) {
            // Si la parada es el final del recorrido, el colectivo termina ahí: nadie se sube, no se anuncia.
            if ($ramal->pivot->orden === $rutas[$ramal->id]->paradas[array_key_last($rutas[$ramal->id]->paradas)]['orden']) {
                continue;
            }

            $limite = $this->segundosHastaFinDeServicio($ramal->linea_id, $ahora);
            $deEsteRamal = [];

            foreach ($posiciones->where('colectivo.linea_id', $ramal->linea_id) as $posicion) {
                $llegada = $estimador->llegada(
                    new Seguimiento(
                        colectivoId: $posicion->colectivo_id,
                        ramalId: $posicion->ramal_id,
                        distanciaM: $posicion->distancia_m,
                        esperaS: $posicion->espera_s,
                        estado: $posicion->estado,
                        velocidadMediaMs: $posicion->velocidad_media_ms,
                        incidente: $posicion->incidente_tipo,
                        desvioOrden: $posicion->desvio_orden,
                    ),
                    $ramal->id,
                    (float) $ramal->pivot->distancia_m,
                );

                // Si el servicio de la línea termina antes de que llegue, ese colectivo ya no va a pasar:
                // para esa parada, este era el último del día.
                if ($limite !== null && $llegada->segundos > $limite) {
                    continue;
                }

                $deEsteRamal[] = [
                    'linea' => $ramal->linea->numero,
                    'ramal_id' => $ramal->id,
                    'destino' => $ramal->destino,
                    'interno' => $posicion->colectivo->interno,
                    'segundos' => (int) round($llegada->segundos),
                    'incierta' => $llegada->incierta,
                    'estado' => $posicion->estado,
                ];
            }

            usort($deEsteRamal, fn ($a, $b) => $a['segundos'] <=> $b['segundos']);
            array_push($llegadas, ...array_slice($deEsteRamal, 0, self::POR_RAMAL));
        }

        usort($llegadas, fn ($a, $b) => $a['segundos'] <=> $b['segundos']);

        return [
            'parada' => ['id' => $parada->id, 'nombre' => $parada->nombre],
            'calculado_en' => $ahora->toIso8601String(),
            'servicio' => $llegadas === [] ? 'sin_servicio' : 'en_servicio',
            'proximo_servicio' => $llegadas === [] ? $this->proximoInicio($lineaIds->all(), $ahora) : null,
            'llegadas' => $llegadas,
        ];
    }

    /** Segundos que faltan para que termine el servicio de hoy de la línea; null si no termina (modo demo). */
    public function segundosHastaFinDeServicio(int $lineaId, Carbon $ahora): ?int
    {
        if (config('ramal.siempre_en_servicio')) {
            return null;
        }

        $horario = $this->horarioDe($lineaId, $ahora);
        if (! $horario) {
            return 0;
        }

        $local = $ahora->copy()->setTimezone(config('ramal.zona_horaria'));
        $fin = $local->copy()->setTimeFromTimeString($horario->hasta);

        return max(0, (int) $local->diffInSeconds($fin, false));
    }

    /** A qué hora vuelve a haber servicio en alguna de las líneas ("05:30"), si hoy ya no hay. */
    private function proximoInicio(array $lineaIds, Carbon $ahora): ?string
    {
        if (config('ramal.siempre_en_servicio')) {
            return null;
        }

        $local = $ahora->copy()->setTimezone(config('ramal.zona_horaria'));

        $inicios = collect($lineaIds)
            ->map(fn ($id) => $this->horarioDe($id, $local))
            ->filter()
            ->map(fn (Horario $h) => substr($h->desde, 0, 5));

        return $inicios->isEmpty() ? null : $inicios->min();
    }

    private function horarioDe(int $lineaId, Carbon $ahora): ?Horario
    {
        $local = $ahora->copy()->setTimezone(config('ramal.zona_horaria'));
        $dia = match ($local->dayOfWeekIso) {
            6 => 'sabado',
            7 => 'domingo',
            default => 'habil',
        };

        return Horario::where('linea_id', $lineaId)->where('dia', $dia)->first();
    }
}
