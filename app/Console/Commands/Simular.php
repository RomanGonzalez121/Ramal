<?php

namespace App\Console\Commands;

use App\Models\Posicion;
use App\Models\Simulacion;
use App\Simulacion\ServicioSimulacion;
use Illuminate\Console\Command;

class Simular extends Command
{
    protected $signature = 'ramal:simular
        {--durante=0 : Segundos que corre antes de terminar. 0 = para siempre}
        {--intervalo= : Segundos entre ticks (por defecto, config ramal.intervalo_s)}
        {--reiniciar : Borra las posiciones y vuelve al tick 0}';

    protected $description = 'Mueve a los colectivos por sus recorridos y avisa a quien los esté mirando';

    public function handle(ServicioSimulacion $servicio): int
    {
        if ($this->option('reiniciar')) {
            Posicion::query()->delete();
            Simulacion::actual()->update(['tick' => 0, 'semilla' => config('ramal.semilla')]);
            $this->info('Simulación reiniciada.');
        }

        $servicio->preparar();

        $intervalo = (float) ($this->option('intervalo') ?: config('ramal.intervalo_s'));
        $limite = (float) $this->option('durante');
        $inicio = microtime(true);
        $ultimo = $inicio;

        $this->info("Simulando cada {$intervalo} s".($limite > 0 ? " durante {$limite} s" : '').'.');

        while (true) {
            $ahora = microtime(true);
            // Si la máquina se atrasó, el tiempo simulado alcanza al real (hasta un máximo para no dar saltos enormes).
            $dt = min($ahora - $ultimo, $intervalo * 5);
            $ultimo = $ahora;

            $servicio->tick($dt);

            if ($limite > 0 && ($ahora - $inicio) >= $limite) {
                break;
            }

            $espera = $intervalo - (microtime(true) - $ahora);
            if ($espera > 0) {
                usleep((int) ($espera * 1_000_000));
            }
        }

        return self::SUCCESS;
    }
}
