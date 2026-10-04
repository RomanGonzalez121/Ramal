<?php

namespace App\Simulacion;

/**
 * El corazón del simulador (M2): hace avanzar a un colectivo por su recorrido.
 *
 * Es PHP puro: no usa Laravel ni la base. Recibe los recorridos ya armados y devuelve estados nuevos,
 * así que se prueba por completo con PHPUnit. Es determinista: toda la aleatoriedad sale de `Azar`,
 * que depende de (semilla, colectivo, tick). Misma semilla, misma corrida.
 *
 * Lo que simula en cada tick, en este orden:
 *  1. Incidentes: puede empezar uno (demora, desvío o falla) o terminar el que estaba en curso.
 *  2. Esperas: en cada parada se detiene unos segundos; en la terminal espera más y cambia de sentido.
 *  3. Movimiento: acelera hacia una velocidad de crucero que varía, y frena para llegar justo a la parada.
 */
final class Simulador
{
    /** Probabilidad por segundo de que empiece cada incidente (cada colectivo, aproximadamente cada tanto). */
    public const TASAS = [
        'demora' => 1 / 10800,  // una cada 3 horas
        'desvio' => 1 / 21600,  // una cada 6 horas
        'falla' => 1 / 64800,   // una cada 18 horas
    ];

    /** Cuánto dura cada incidente, en segundos: [mínimo, máximo]. */
    public const DURACION = [
        'demora' => [120, 300],
        'desvio' => [240, 480],
        'falla' => [180, 600],
    ];

    public const ACELERACION = 1.0;   // m/s²

    public const FRENADO = 1.4;       // m/s²

    /**
     * @param  array<int, RutaSimulada>  $rutas  por id de ramal
     */
    public function __construct(
        private readonly int $semilla,
        private readonly array $rutas,
    ) {}

    /**
     * Pone al colectivo número `$indice` (de `$total` en su línea) repartido a lo largo del ciclo ida + vuelta.
     */
    public function estadoInicial(int $colectivoId, int $ramalIdaId, int $indice, int $total): EstadoColectivo
    {
        $ida = $this->rutas[$ramalIdaId];
        $vuelta = $this->rutas[$ida->ramalOpuestoId];
        $ciclo = $ida->largoMetros() + $vuelta->largoMetros();

        $s = (($indice + Azar::entre($this->semilla, $colectivoId, 0, 1, 0.0, 0.35)) / $total) * $ciclo;
        [$ramal, $distancia] = $s < $ida->largoMetros() ? [$ida, $s] : [$vuelta, $s - $ida->largoMetros()];

        return new EstadoColectivo(
            colectivoId: $colectivoId,
            ramalId: $ramal->ramalId,
            distanciaM: $distancia,
            velocidadMs: 0.0,
            estado: EstadoColectivo::CIRCULANDO,
            esperaS: 0.0,
            ultimaParada: $ramal->paradaHasta($distancia),
            velocidadCrucero: Azar::entre($this->semilla, $colectivoId, 0, 0, 4.5, 7.5),
        );
    }

    /**
     * Un colectivo que sale de la terminal después de `$retrasoS` segundos (cuando empieza el servicio del día).
     */
    public function estadoEnTerminal(int $colectivoId, int $ramalIdaId, float $retrasoS): EstadoColectivo
    {
        return new EstadoColectivo(
            colectivoId: $colectivoId,
            ramalId: $ramalIdaId,
            distanciaM: 0.0,
            velocidadMs: 0.0,
            estado: EstadoColectivo::EN_PARADA,
            esperaS: $retrasoS,
            ultimaParada: 1,
            velocidadCrucero: Azar::entre($this->semilla, $colectivoId, 0, 0, 4.5, 7.5),
        );
    }

    /**
     * Avanza `$dt` segundos. Devuelve el estado nuevo y los eventos ocurridos (incidentes que empiezan o terminan).
     *
     * @return array{0: EstadoColectivo, 1: array<int, array{tipo: string, incidente: string, duracion_s?: float}>}
     */
    public function avanzar(EstadoColectivo $e, int $tick, float $dt): array
    {
        $azar = fn (int $i): float => Azar::flotante($this->semilla, $e->colectivoId, $tick, $i);

        $ruta = $this->rutas[$e->ramalId];
        $eventos = [];

        $estado = $e->estado;
        $espera = $e->esperaS;
        $v = $e->velocidadMs;
        $d = $e->distanciaM;
        $ultima = $e->ultimaParada;
        $ramalId = $e->ramalId;
        $incidente = $e->incidente;
        $restante = $e->incidenteRestanteS;
        $desvioOrden = $e->desvioOrden;

        // 1. Incidentes.
        if ($incidente !== null) {
            // Un desvío no se acaba por el paso del tiempo: termina cuando el colectivo vuelve al recorrido.
            if ($incidente !== 'desvio') {
                $restante -= $dt;
                if ($restante <= 0) {
                    $eventos[] = ['tipo' => 'resuelto', 'incidente' => $incidente];
                    $incidente = null;
                    $restante = 0.0;
                }
            }
        } elseif ($espera <= 0) {
            $i = 0;
            foreach (self::TASAS as $tipo => $tasa) {
                if ($azar(10 + $i++) >= $tasa * $dt) {
                    continue;
                }

                if ($tipo === 'desvio') {
                    // Sale por otras calles en el tramo que sigue; si ahí no hay camino alternativo, no hay desvío.
                    $camino = $ruta->desvio($ultima + 1);
                    if ($camino === null) {
                        continue;
                    }

                    $incidente = 'desvio';
                    $desvioOrden = $ultima + 1;
                    $restante = $camino['largo_m'] / max(1.0, $e->velocidadCrucero * 0.85);
                    $eventos[] = ['tipo' => 'nuevo', 'incidente' => 'desvio', 'duracion_s' => $restante];
                    break;
                }

                [$min, $max] = self::DURACION[$tipo];
                $incidente = $tipo;
                $restante = $min + $azar(20) * ($max - $min);
                $eventos[] = ['tipo' => 'nuevo', 'incidente' => $tipo, 'duracion_s' => $restante];
                break;
            }
        }

        // 2 y 3. Esperas y movimiento.
        if ($espera > 0) {
            $espera -= $dt;
            $v = 0.0;

            if ($espera <= 0) {
                $espera = 0.0;

                if ($estado === EstadoColectivo::EN_TERMINAL) {
                    // Terminó la espera en la terminal: sale en sentido contrario, desde el principio.
                    $ramalId = $ruta->ramalOpuestoId;
                    $ruta = $this->rutas[$ramalId];
                    $d = 0.0;
                    $ultima = 1;
                }

                $estado = EstadoColectivo::CIRCULANDO;
            }
        } elseif ($incidente === 'falla') {
            $v = 0.0;
        } else {
            // Por el camino alternativo anda más despacio: calles que no conoce y más giros.
            $enDesvio = $ruta->enDesvio($d, $desvioOrden);
            $factor = match (true) {
                $incidente === 'demora' => 0.35,
                $enDesvio => 0.85,
                default => 1.0,
            };
            $crucero = $e->velocidadCrucero * $factor * (0.8 + 0.4 * $azar(1));

            // En el desvío, cada metro real vale menos metros del recorrido normal (el camino alternativo es más largo).
            $escala = $ruta->escalaEn($d, $desvioOrden);

            $siguiente = $ruta->siguienteParada($ultima);
            $falta = $siguiente !== null ? max(0.0, $siguiente['distancia_m'] - $d) / $escala : INF;

            // Frena de modo de llegar a la parada con velocidad cercana a cero.
            $objetivo = $falta === INF ? $crucero : min($crucero, max(0.8, sqrt(2 * self::FRENADO * $falta)));

            $v = $objetivo > $v
                ? min($objetivo, $v + self::ACELERACION * $dt)
                : max($objetivo, $v - self::FRENADO * $dt);

            $paso = $v * $dt * $escala;

            if ($siguiente !== null && $d + $paso >= $siguiente['distancia_m'] - 0.5) {
                $d = (float) $siguiente['distancia_m'];
                $v = 0.0;
                $ultima = $siguiente['orden'];

                // Llegó a la parada donde el desvío vuelve al recorrido: se termina.
                if ($desvioOrden !== null && $ultima >= $desvioOrden + 1) {
                    $eventos[] = ['tipo' => 'resuelto', 'incidente' => 'desvio'];
                    $incidente = null;
                    $desvioOrden = null;
                    $restante = 0.0;
                }

                if ($ruta->siguienteParada($ultima) === null) {
                    $estado = EstadoColectivo::EN_TERMINAL;
                    $espera = 60 + $azar(2) * 90;
                } else {
                    $estado = EstadoColectivo::EN_PARADA;
                    $espera = 8 + $azar(3) * 17;
                }
            } else {
                $d += $paso;
                $estado = EstadoColectivo::CIRCULANDO;
            }
        }

        $visible = match (true) {
            $estado === EstadoColectivo::EN_TERMINAL, $estado === EstadoColectivo::EN_PARADA => $estado,
            $incidente === 'falla' => EstadoColectivo::AVERIADO,
            $ruta->enDesvio($d, $desvioOrden) => EstadoColectivo::FUERA_DE_RECORRIDO,
            $incidente === 'demora' => EstadoColectivo::DEMORADO,
            default => EstadoColectivo::CIRCULANDO,
        };

        return [
            $e->con([
                'ramalId' => $ramalId,
                'distanciaM' => $d,
                'velocidadMs' => $v,
                'estado' => $visible,
                'esperaS' => $espera,
                'ultimaParada' => $ultima,
                'incidente' => $incidente,
                'incidenteRestanteS' => $restante,
                'desvioOrden' => $desvioOrden,
            ]),
            $eventos,
        ];
    }

    /**
     * Una orden del operador sobre el incidente en curso del colectivo.
     *  - 'atender': el operador habló con el chofer o mandó ayuda; el problema se resuelve antes (una falla en 2 min como
     *    mucho, una demora en 1 min). No afecta a un desvío.
     *  - 'resolver': el operador da por terminado el problema ya. No se puede dar por resuelto un desvío.
     *
     * @return array{0: EstadoColectivo, 1: bool} el estado nuevo y si el incidente quedó resuelto
     */
    public function aplicarOrden(EstadoColectivo $e, string $orden): array
    {
        if ($e->incidente === null || $e->incidente === 'desvio') {
            return [$e, false];
        }

        if ($orden === 'resolver') {
            return [$e->con(['incidente' => null, 'incidenteRestanteS' => 0.0]), true];
        }

        $tope = $e->incidente === 'falla' ? 120.0 : 60.0;

        return [$e->con(['incidenteRestanteS' => min($e->incidenteRestanteS, $tope)]), false];
    }
}
