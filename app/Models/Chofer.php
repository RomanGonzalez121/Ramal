<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Los choferes son inventados, y el sitio lo dice.
 */
class Chofer extends Model
{
    protected $table = 'choferes';

    protected $fillable = ['nombre', 'legajo'];

    public function colectivos(): HasMany
    {
        return $this->hasMany(Colectivo::class, 'chofer_id');
    }
}
