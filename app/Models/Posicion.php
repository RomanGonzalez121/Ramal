<?php

namespace App\Models;

use App\Simulacion\EstadoColectivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Posicion extends Model
{
    public $timestamps = false;

    protected $table = 'posiciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'distancia_m' => 'float',
            'velocidad_ms' => 'float',
            'velocidad_media_ms' => 'float',
            'espera_s' => 'float',
            'velocidad_crucero' => 'float',
            'incidente_restante_s' => 'float',
            'latitud' => 'float',
            'longitud' => 'float',
            'rumbo' => 'integer',
            'actualizado_en' => 'datetime',
        ];
    }

    public function colectivo(): BelongsTo
    {
        return $this->belongsTo(Colectivo::class, 'colectivo_id');
    }

    public function ramal(): BelongsTo
    {
        return $this->belongsTo(Ramal::class, 'ramal_id');
    }

    public function aEstado(): EstadoColectivo
    {
        return new EstadoColectivo(
            colectivoId: $this->colectivo_id,
            ramalId: $this->ramal_id,
            distanciaM: $this->distancia_m,
            velocidadMs: $this->velocidad_ms,
            estado: $this->estado,
            esperaS: $this->espera_s,
            ultimaParada: $this->ultima_parada,
            velocidadCrucero: $this->velocidad_crucero,
            incidente: $this->incidente_tipo,
            incidenteRestanteS: $this->incidente_restante_s,
            desvioOrden: $this->desvio_orden,
        );
    }
}
