<?php

namespace App\Estimaciones;

use App\Simulacion\EstadoColectivo;
use App\Simulacion\RutaSimulada;

/**
 * M5: cuántos segundos faltan para que un colectivo llegue a una parada.
 *
 * PHP puro, sin base ni Laravel. La cuenta recorre el camino que le falta al colectivo, metro a metro, y suma:
 *  - el tiempo de andar cada tramo, a la velocidad media que viene teniendo (no a la del cartel),
 *  - lo que se demora en cada parada intermedia (promedio),
 *  - lo que le queda de espera si está detenido,
 *  - y, si la parada quedó atrás o está en la otra mano, la vuelta completa con las esperas en las terminales.
 *
 * Casos borde que resuelve de forma explícita: recién pasó por la parada, está detenido justo en ella,
 * viene por el ramal contrario, está esperando en la terminal, está demorado (su velocidad media ya lo refleja)
 * y tiene una falla (se suma un tiempo típico de arreglo y la cuenta se marca como incierta).
 */
final class Estimador
{
    /** Cuánto se detiene, en promedio, en una parada: el simulador usa entre 8 y 25 s. */
    public const PARADA_PROMEDIO_S = 16.5;

    /** Espera promedio en la terminal: el simulador usa entre 60 y 150 s. */
    public const TERMINAL_PROMEDIO_S = 105.0;

    /** Tiempo típico de arreglo que se suma cuando hay una falla (no se conoce la hora real de arreglo). */
    public const ARREGLO_TIPICO_S = 300.0;

    /** Para no dividir por casi cero si el colectivo viene parado hace rato. */
    public const VELOCIDAD_MINIMA_MS = 1.5;

    /** Más cerca que esto de la parada se considera "ya está en la parada". */
    public const TOLERANCIA_M = 3.0;

    /** Por el camino alternativo anda más despacio (el simulador usa 0,85 de su velocidad). */
    public const FACTOR_VELOCIDAD_EN_DESVIO = 0.85;

    /**
     * @param  array<int, RutaSimulada>  $rutas  por id de ramal
     */
    public function __construct(private readonly array $rutas) {}

    /**
     * @param  int  $ramalParadaId  ramal en el que se mira la parada
     * @param  float  $distanciaParadaM  a cuántos metros del inicio de ese ramal está la parada
     */
    public function llegada(Seguimiento $c, int $ramalParadaId, float $distanciaParadaM): Llegada
    {
        $v = max(self::VELOCIDAD_MINIMA_MS, $c->velocidadMediaMs);
        $ruta = $this->rutas[$c->ramalId];
        $destino = $this->rutas[$ramalParadaId];
        $detenido = $c->esperaS > 0 ? $c->esperaS : 0.0;
        $enTerminal = $c->estado === EstadoColectivo::EN_TERMINAL;

        if ($c->ramalId === $ramalParadaId) {
            $falta = $distanciaParadaM - $c->distanciaM;

            // Está en la parada (o a pocos metros): llegó.
            if (abs($falta) <= self::TOLERANCIA_M) {
                return $this->conIncidente($c, new Llegada($c->colectivoId, 0.0));
            }

            // La parada está por delante.
            if ($falta > 0) {
                $segundos = $falta / $v
                    + $this->paradasEntre($ruta, $c->distanciaM, $distanciaParadaM) * self::PARADA_PROMEDIO_S
                    + $detenido
                    + $this->extraPorDesvio($c, $ruta, $v, $distanciaParadaM);

                return $this->conIncidente($c, new Llegada($c->colectivoId, $segundos));
            }

            // Recién pasó: tiene que terminar este ramal, dar la vuelta completa y volver a empezar.
            $segundos = $this->hastaElFinal($c, $ruta, $v, $detenido, $enTerminal)
                + $this->extraPorDesvio($c, $ruta, $v, INF)
                + $this->ramalCompleto($ruta->ramalOpuestoId, $v)
                + $this->desdeElInicio($ruta, $distanciaParadaM, $v);

            return $this->conIncidente($c, new Llegada($c->colectivoId, $segundos));
        }

        // Viene por el ramal contrario: termina ese ramal, espera en la terminal y sale hacia la parada.
        $segundos = $this->hastaElFinal($c, $ruta, $v, $detenido, $enTerminal)
            + $this->extraPorDesvio($c, $ruta, $v, INF)
            + $this->desdeElInicio($destino, $distanciaParadaM, $v);

        return $this->conIncidente($c, new Llegada($c->colectivoId, $segundos));
    }

    /**
     * Tiempo de más que le va a llevar un desvío que todavía no empezó. Una vez que entró al camino alternativo no
     * hace falta sumar nada: su velocidad media ya se mide en metros del recorrido y refleja el desvío.
     *
     * @param  float  $hastaDonde  metros del ramal hasta donde se mira (INF si va a terminar el ramal)
     */
    private function extraPorDesvio(Seguimiento $c, RutaSimulada $ruta, float $v, float $hastaDonde): float
    {
        $desvio = $c->desvioOrden !== null ? $ruta->desvio($c->desvioOrden) : null;

        if ($desvio === null || $c->distanciaM >= $desvio['desde_m'] || $hastaDonde < $desvio['hasta_m'] - self::TOLERANCIA_M) {
            return 0.0;
        }

        $normal = ($desvio['hasta_m'] - $desvio['desde_m']) / $v;
        $porElDesvio = $desvio['largo_m'] / (self::FACTOR_VELOCIDAD_EN_DESVIO * $v);

        return max(0.0, $porElDesvio - $normal);
    }

    /** Lo que falta para terminar el ramal en el que está, más la espera en la terminal. */
    private function hastaElFinal(Seguimiento $c, RutaSimulada $ruta, float $v, float $detenido, bool $enTerminal): float
    {
        if ($enTerminal) {
            // Ya está en la terminal: le queda lo que falte de la espera.
            return $detenido;
        }

        $segundos = max(0.0, $ruta->largoMetros() - $c->distanciaM) / $v
            + $this->paradasEntre($ruta, $c->distanciaM, $ruta->largoMetros()) * self::PARADA_PROMEDIO_S
            + self::TERMINAL_PROMEDIO_S;

        // Si está detenido en una parada del camino, termina esa espera antes de seguir.
        return $segundos + $detenido;
    }

    /** Recorrer un ramal entero (el que no es el del colectivo) y esperar en su terminal. */
    private function ramalCompleto(int $ramalOpuestoId, float $v): float
    {
        $otro = $this->rutas[$ramalOpuestoId];

        return $otro->largoMetros() / $v
            + $this->paradasEntre($otro, 0.0, $otro->largoMetros()) * self::PARADA_PROMEDIO_S
            + self::TERMINAL_PROMEDIO_S;
    }

    /** Salir de la terminal y llegar hasta `$metros` del inicio, con las paradas del camino. */
    private function desdeElInicio(RutaSimulada $ruta, float $metros, float $v): float
    {
        return $metros / $v + $this->paradasEntre($ruta, 0.0, $metros) * self::PARADA_PROMEDIO_S;
    }

    /** Cuántas paradas hay estrictamente entre dos puntos del ramal (sin contar la del destino). */
    private function paradasEntre(RutaSimulada $ruta, float $desde, float $hasta): int
    {
        $cantidad = 0;
        foreach ($ruta->paradas as $parada) {
            if ($parada['distancia_m'] > $desde + self::TOLERANCIA_M && $parada['distancia_m'] < $hasta - self::TOLERANCIA_M) {
                $cantidad++;
            }
        }

        return $cantidad;
    }

    private function conIncidente(Seguimiento $c, Llegada $llegada): Llegada
    {
        if ($c->incidente !== 'falla' && $c->estado !== EstadoColectivo::AVERIADO) {
            return $llegada;
        }

        return new Llegada($llegada->colectivoId, $llegada->segundos + self::ARREGLO_TIPICO_S, true);
    }
}
