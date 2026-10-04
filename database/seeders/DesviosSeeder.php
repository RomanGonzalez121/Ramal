<?php

namespace Database\Seeders;

use App\Models\Ramal;
use App\Support\Geo;
use Illuminate\Database\Seeder;

/**
 * Carga los caminos alternativos entre paradas (ya calculados, ver scripts/calcular-desvios.mjs).
 * Se puede correr solo, sin tocar el resto de la ciudad: `php artisan db:seed --class=DesviosSeeder`.
 */
class DesviosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('datos/desvios.json');

        if (! is_file($ruta)) {
            $this->command?->warn('No existe database/datos/desvios.json: no hay desvíos. Corré scripts/calcular-desvios.mjs.');

            return;
        }

        $datos = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $ramales = Ramal::with('linea')->get()->mapWithKeys(fn (Ramal $r) => ["{$r->linea->numero}-{$r->sentido}" => $r]);

        foreach ($datos['ramales'] as $clave => $tramos) {
            $ramal = $ramales[$clave] ?? null;

            if (! $ramal) {
                continue;
            }

            foreach ($tramos as $orden => $camino) {
                $distancias = Geo::distanciasAcumuladas($camino['puntos']);

                $ramal->desvios()->updateOrCreate(
                    ['desde_orden' => (int) $orden],
                    [
                        'puntos' => $camino['puntos'],
                        'distancias' => array_map(fn (float $m) => round($m, 1), $distancias),
                        'largo_m' => (int) round(end($distancias)),
                    ],
                );
            }
        }
    }
}
