<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Parada extends Model
{
    protected $table = 'paradas';

    protected $fillable = ['nombre', 'latitud', 'longitud'];

    protected function casts(): array
    {
        return ['latitud' => 'float', 'longitud' => 'float'];
    }

    public function ramales(): BelongsToMany
    {
        return $this->belongsToMany(Ramal::class, 'parada_ramal', 'parada_id', 'ramal_id')
            ->withPivot('orden', 'distancia_m');
    }
}
