<?php

namespace App\Models;

use App\Historial\Instantanea;
use Illuminate\Database\Eloquent\Model;

/**
 * Una foto del historial: dónde estaba cada colectivo en un momento. Se guarda cada ~10 s y se borra a las 48 h.
 */
class HistorialPosicion extends Model
{
    public $timestamps = false;

    protected $table = 'historial_posiciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['momento' => 'datetime'];
    }

    /** @return array<int, array{id: int, ramal: int, lon: float, lat: float, rumbo: int, estado: string}> */
    public function colectivos(): array
    {
        return Instantanea::desempaquetar($this->datos);
    }
}
