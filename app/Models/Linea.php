<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Linea extends Model
{
    protected $table = 'lineas';

    protected $fillable = ['numero', 'nombre', 'destino'];

    public function ramales(): HasMany
    {
        return $this->hasMany(Ramal::class, 'linea_id');
    }

    public function colectivos(): HasMany
    {
        return $this->hasMany(Colectivo::class, 'linea_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'linea_id');
    }
}
