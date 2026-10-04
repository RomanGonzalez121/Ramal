<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un problema en un colectivo. Su ciclo de vida (M7): se genera (activo), un operador lo ve y lo
 * atiende (atendido), y se resuelve (por el paso del tiempo, por el operador, o porque el colectivo vuelve al recorrido).
 */
class Incidente extends Model
{
    public const ACTIVO = 'activo';

    public const ATENDIDO = 'atendido';

    public const RESUELTO = 'resuelto';

    protected $table = 'incidentes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inicio_en' => 'datetime',
            'fin_en' => 'datetime',
            'atendido_en' => 'datetime',
            'ajuste_aplicado' => 'boolean',
        ];
    }

    public function colectivo(): BelongsTo
    {
        return $this->belongsTo(Colectivo::class, 'colectivo_id');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function estaAbierto(): bool
    {
        return $this->estado !== self::RESUELTO;
    }

    /** Un desvío se cierra solo cuando el colectivo vuelve al recorrido: el operador no puede darlo por resuelto. */
    public function puedeResolverseAMano(): bool
    {
        return $this->estaAbierto() && $this->tipo !== 'desvio' && $this->accion_pedida === null;
    }
}
