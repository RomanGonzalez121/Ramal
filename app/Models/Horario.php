<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio de una línea según el tipo de día: desde cuándo, hasta cuándo y cada cuántos minutos sale un colectivo.
 */
class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = ['linea_id', 'dia', 'desde', 'hasta', 'frecuencia_min'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }
}
