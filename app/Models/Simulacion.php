<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * La fila única que dice con qué semilla corre la simulación y en qué tick va.
 */
class Simulacion extends Model
{
    public $incrementing = false;

    protected $table = 'simulacion';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ultimo_tick_en' => 'datetime'];
    }

    public static function actual(): self
    {
        return self::firstOrCreate(['id' => 1], ['semilla' => config('ramal.semilla'), 'tick' => 0]);
    }
}
