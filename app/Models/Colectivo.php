<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Colectivo extends Model
{
    protected $table = 'colectivos';

    protected $fillable = ['linea_id', 'chofer_id', 'interno', 'capacidad'];

    public function linea(): BelongsTo
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class, 'chofer_id');
    }
}
