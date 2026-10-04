<?php

namespace App\Simulacion;

/**
 * Todo lo que hace falta saber de un colectivo para seguir la simulación. Inmutable: cada tick devuelve uno nuevo.
 */
final class EstadoColectivo
{
    public const CIRCULANDO = 'circulando';

    public const EN_PARADA = 'en_parada';

    public const EN_TERMINAL = 'en_terminal';

    public const DEMORADO = 'demorado';

    public const AVERIADO = 'averiado';

    public const FUERA_DE_RECORRIDO = 'fuera_de_recorrido';

    public const FUERA_DE_SERVICIO = 'fuera_de_servicio';

    public function __construct(
        public readonly int $colectivoId,
        public readonly int $ramalId,
        public readonly float $distanciaM,
        public readonly float $velocidadMs,
        public readonly string $estado,
        public readonly float $esperaS,
        public readonly int $ultimaParada,
        public readonly float $velocidadCrucero,
        public readonly ?string $incidente = null,
        public readonly float $incidenteRestanteS = 0.0,
        /** Orden de la parada de la que sale el desvío en curso, o null si no hay ninguno. */
        public readonly ?int $desvioOrden = null,
    ) {}

    public function con(array $cambios): self
    {
        return new self(...array_merge(get_object_vars($this), $cambios));
    }
}
