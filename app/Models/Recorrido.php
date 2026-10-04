<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El trazado de un ramal sobre calles reales: puntos [longitud, latitud] y los metros
 * acumulados hasta cada uno. Se calcula una vez fuera de la app (scripts/calcular-recorridos.mjs).
 */
class Recorrido extends Model
{
    protected $table = 'recorridos';

    protected $fillable = ['ramal_id', 'puntos', 'distancias', 'largo_m', 'fuente', 'calculado_el'];

    protected function casts(): array
    {
        return [
            'puntos' => 'array',
            'distancias' => 'array',
            'calculado_el' => 'date',
        ];
    }

    public function ramal(): BelongsTo
    {
        return $this->belongsTo(Ramal::class, 'ramal_id');
    }
}
