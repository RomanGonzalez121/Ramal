<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un camino alternativo entre dos paradas consecutivas de un ramal. Se calcula una vez fuera de la app
 * (scripts/calcular-desvios.mjs) y el simulador lo usa cuando un colectivo sufre un desvío.
 */
class Desvio extends Model
{
    protected $table = 'desvios';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['puntos' => 'array', 'distancias' => 'array'];
    }

    public function ramal(): BelongsTo
    {
        return $this->belongsTo(Ramal::class, 'ramal_id');
    }
}
