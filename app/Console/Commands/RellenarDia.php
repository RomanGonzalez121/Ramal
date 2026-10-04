<?php

namespace App\Console\Commands;

use App\Models\Colectivo;
use App\Models\Incidente;
use App\Models\Simulacion;
use App\Simulacion\ServicioSimulacion;
use App\Simulacion\Simulador;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rellena las horas de hoy anteriores a que arrancara la simulación en vivo, para que el panel de operador
 * tenga un día completo que mostrar. Corre el mismo simulador de siempre, a alta velocidad y sin tocar
 * las posiciones actuales. Los incidentes quedan marcados con origen "relleno".
 */
class RellenarDia extends Command
{
    protected $signature = 'ramal:rellenar-dia
        {--desde=05:30 : Hora de Paraná desde la que se rellena}
        {--rehacer : Borra el relleno de hoy y lo vuelve a generar}';

    protected $description = 'Genera los incidentes de las horas de hoy anteriores al arranque de la simulación en vivo';

    public function handle(ServicioSimulacion $servicio): int
    {
        $zona = config('ramal.zona_horaria');
        $ahora = now();
        $inicioDia = $ahora->copy()->setTimezone($zona)->startOfDay()->setTimezone('UTC');

        if ($this->option('rehacer')) {
            $borrados = Incidente::where('origen', 'relleno')->where('inicio_en', '>=', $inicioDia)->delete();
            $this->info("Se borraron {$borrados} incidentes de relleno.");
        }

        $desde = $ahora->copy()->setTimezone($zona)->setTimeFromTimeString($this->option('desde'))->setTimezone('UTC');
        $primero = Incidente::where('origen', 'simulacion')->where('inicio_en', '>=', $inicioDia)->min('inicio_en');
        $hasta = $primero ? Carbon::parse($primero, 'UTC') : $ahora;

        if ($hasta->lessThanOrEqualTo($desde)) {
            $this->warn('No hay horas anteriores para rellenar.');

            return self::SUCCESS;
        }

        if (Incidente::where('origen', 'relleno')->where('inicio_en', '>=', $inicioDia)->exists()) {
            $this->warn('Ya hay relleno de hoy. Usá --rehacer para generarlo de nuevo.');

            return self::SUCCESS;
        }

        $semilla = Simulacion::actual()->semilla;
        $simulador = new Simulador($semilla, $servicio->rutas());
        $colectivos = Colectivo::with('linea.ramales')->orderBy('interno')->get()->groupBy('linea_id');

        $paso = 10.0;
        $ticks = (int) floor($desde->diffInSeconds($hasta) / $paso);
        $filas = [];

        foreach ($colectivos as $deLaLinea) {
            foreach ($deLaLinea->values() as $indice => $colectivo) {
                $ida = $colectivo->linea->ramales->firstWhere('sentido', 'ida');
                $estado = $simulador->estadoInicial($colectivo->id, $ida->id, $indice, $deLaLinea->count());
                $abierto = null;

                for ($t = 1; $t <= $ticks; $t++) {
                    // Los números de tick de acá no se pisan con los de la simulación en vivo.
                    [$estado, $eventos] = $simulador->avanzar($estado, 5_000_000 + $t, $paso);
                    $momento = $desde->copy()->addSeconds($t * $paso);

                    foreach ($eventos as $evento) {
                        if ($evento['tipo'] === 'nuevo') {
                            $abierto = ['tipo' => $evento['incidente'], 'inicio' => $momento, 'duracion' => (int) $evento['duracion_s']];
                        } elseif ($abierto) {
                            $filas[] = $this->fila($colectivo->id, $abierto, $momento);
                            $abierto = null;
                        }
                    }
                }

                // Lo que seguía abierto al llegar al límite no se guarda: lo toma la simulación en vivo.
            }
        }

        foreach (array_chunk($filas, 200) as $lote) {
            Incidente::insert($lote);
        }

        $this->info(count($filas).' incidentes generados entre '.$desde->setTimezone($zona)->format('H:i').' y '.$hasta->setTimezone($zona)->format('H:i').'.');

        return self::SUCCESS;
    }

    /** @param array{tipo: string, inicio: Carbon, duracion: int} $abierto */
    private function fila(int $colectivoId, array $abierto, Carbon $fin): array
    {
        return [
            'colectivo_id' => $colectivoId,
            'tipo' => $abierto['tipo'],
            'estado' => 'resuelto',
            'origen' => 'relleno',
            'duracion_prevista_s' => $abierto['duracion'],
            'inicio_en' => $abierto['inicio'],
            'fin_en' => $fin,
            'created_at' => $abierto['inicio'],
            'updated_at' => $fin,
        ];
    }
}
