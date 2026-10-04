<?php

namespace App\Console\Commands;

use App\Models\HistorialPosicion;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\Simulacion;
use App\Simulacion\ServicioSimulacion;
use Illuminate\Console\Command;

/**
 * Rehace el día de hoy: corre el simulador a alta velocidad desde la hora elegida hasta ahora, sin emitir nada.
 * Deja, de una sola vez y de forma coherente:
 *  - el historial para rebobinar (una foto cada 10 s),
 *  - los incidentes de hoy (con su ciclo completo) para el centro de control,
 *  - y las posiciones actuales, que quedan donde terminó la corrida: la simulación en vivo sigue desde ahí.
 *
 * Es lo mismo que haría el servidor si hubiera estado prendido todo el día.
 */
class RellenarDia extends Command
{
    protected $signature = 'ramal:rellenar-dia
        {--desde=05:30 : Hora de Paraná desde la que se rehace}
        {--ultimos= : Rehacer solo los últimos N minutos (en vez de --desde). Es lo que usa el contenedor al despertar}
        {--paso=10 : Segundos simulados por cada paso}
        {--forzar : Seguir aunque la simulación en vivo esté corriendo}';

    protected $description = 'Rehace el día de hoy: historial, incidentes y posiciones, corriendo el simulador a alta velocidad';

    public function handle(ServicioSimulacion $servicio): int
    {
        $sim = Simulacion::actual();

        if (! $this->option('forzar') && $sim->ultimo_tick_en && abs($sim->ultimo_tick_en->diffInSeconds(now())) < 15) {
            $this->error('La simulación en vivo está corriendo. Detenela primero (o usá --forzar): las dos se pisarían las posiciones.');

            return self::FAILURE;
        }

        $zona = config('ramal.zona_horaria');
        $ahora = now()->utc();
        $inicioDia = $ahora->copy()->setTimezone($zona)->startOfDay()->utc();
        $desde = $ahora->copy()->setTimezone($zona)->setTimeFromTimeString($this->option('desde'))->utc();

        if ($this->option('ultimos') !== null) {
            $desde = $ahora->copy()->subMinutes(max(1, (int) $this->option('ultimos')));
        } elseif ($desde->greaterThanOrEqualTo($ahora)) {
            // Todavía no es la hora pedida: se rehace desde hace 3 horas.
            $desde = $ahora->copy()->subHours(3);
            $this->warn('Todavía no son las '.$this->option('desde').'; se rehacen las últimas 3 horas.');
        }

        $paso = (float) $this->option('paso');
        $pasos = (int) floor($desde->diffInSeconds($ahora) / $paso);

        $this->info("Rehaciendo {$pasos} pasos de {$paso} s, desde ".$desde->copy()->setTimezone($zona)->format('H:i').' hasta ahora.');

        // Se empieza de cero: historial, incidentes de hoy y posiciones.
        HistorialPosicion::query()->delete();
        Incidente::where('inicio_en', '>=', $inicioDia)->delete();
        Posicion::query()->delete();
        $sim->update(['tick' => 0, 'ultimo_tick_en' => null]);

        $servicio->reiniciarHistorial();
        $servicio->preparar();
        $servicio->origenIncidentes = 'relleno';

        $barra = $this->output->createProgressBar($pasos);
        $barra->start();

        for ($i = 1; $i <= $pasos; $i++) {
            $servicio->tick($paso, $desde->copy()->addSeconds($i * $paso), emitir: false);
            $barra->advance();
        }

        $barra->finish();
        $this->newLine();

        $servicio->origenIncidentes = 'simulacion';

        $this->info(HistorialPosicion::count().' fotos del historial, '.Incidente::where('inicio_en', '>=', $inicioDia)->count().' incidentes de hoy.');

        return self::SUCCESS;
    }
}
