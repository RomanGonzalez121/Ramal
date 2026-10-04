<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Las posiciones nuevas de los colectivos que están dentro de una celda de la ciudad.
 * Un evento por celda y por tick; solo viaja por el canal de esa celda.
 * Se manda por cola para que el simulador no espere a la red.
 */
class PosicionesActualizadas implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $colectivos
     */
    public function __construct(
        public readonly string $celda,
        public readonly int $tick,
        public readonly array $colectivos,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("posiciones.{$this->celda}");
    }

    public function broadcastAs(): string
    {
        return 'actualizacion';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['tick' => $this->tick, 'celda' => $this->celda, 'colectivos' => $this->colectivos];
    }
}
