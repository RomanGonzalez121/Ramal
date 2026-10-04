<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un ramal es una variante de recorrido de una línea. Hoy: ida y vuelta.
 */
class Ramal extends Model
{
    protected $table = 'ramales';

    protected $fillable = ['linea_id', 'sentido', 'destino'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    /** Paradas en el orden en que las recorre el colectivo. */
    public function paradas(): BelongsToMany
    {
        return $this->belongsToMany(Parada::class, 'parada_ramal', 'ramal_id', 'parada_id')
            ->withPivot('orden', 'distancia_m')
            ->orderByPivot('orden');
    }

    public function recorrido(): HasOne
    {
        return $this->hasOne(Recorrido::class, 'ramal_id');
    }

    /** Caminos alternativos entre paradas consecutivas, para cuando un colectivo sufre un desvío. */
    public function desvios(): HasMany
    {
        return $this->hasMany(Desvio::class, 'ramal_id');
    }
}
