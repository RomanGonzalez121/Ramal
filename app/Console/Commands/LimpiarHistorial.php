<?php

namespace App\Console\Commands;

use App\Models\HistorialPosicion;
use Illuminate\Console\Command;

/**
 * Política de retención (M8): el historial no crece sin límite. Se conserva lo de las últimas
 * `ramal.historial.retencion_horas` horas y se borra lo anterior. Corre una vez por hora desde el programador.
 */
class LimpiarHistorial extends Command
{
    protected $signature = 'ramal:limpiar-historial {--horas= : Horas que se conservan (por defecto, config ramal.historial.retencion_horas)}';

    protected $description = 'Borra las fotos del historial más viejas que la retención';

    public function handle(): int
    {
        $horas = (int) ($this->option('horas') ?: config('ramal.historial.retencion_horas'));
        $limite = now()->subHours($horas);

        $borradas = HistorialPosicion::where('momento', '<', $limite)->delete();

        $this->info("Se borraron {$borradas} fotos de más de {$horas} horas. Quedan ".HistorialPosicion::count().'.');

        return self::SUCCESS;
    }
}
