<?php

namespace Tests\Unit\Estimaciones;

use App\Estimaciones\Estimador;
use App\Estimaciones\Seguimiento;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\Simulador;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Simulacion\RutasDePrueba;

class EstimadorTest extends TestCase
{
    use RutasDePrueba;

    private const V = 5.0;   // m/s, velocidad media de los ejemplos

    private const PARADA = Estimador::PARADA_PROMEDIO_S;

    private const TERMINAL = Estimador::TERMINAL_PROMEDIO_S;

    private function colectivo(int $ramal, float $d, float $espera = 0.0, string $estado = EstadoColectivo::CIRCULANDO, float $v = self::V, ?string $incidente = null): Seguimiento
    {
        return new Seguimiento(1, $ramal, $d, $espera, $estado, $v, $incidente);
    }

    private function segundos(Seguimiento $c, int $ramalParada, float $paradaM): float
    {
        return (new Estimador($this->rutas()))->llegada($c, $ramalParada, $paradaM)->segundos;
    }

    public function test_si_la_parada_esta_adelante_suma_el_camino_y_las_paradas_intermedias(): void
    {
        // De 500 a 2000 m: 1500 m a 5 m/s y una parada en el medio (la de 1000 m).
        $this->assertEqualsWithDelta(1500 / self::V + self::PARADA, $this->segundos($this->colectivo(1, 500), 1, 2000), 0.001);
    }

    public function test_sin_paradas_en_el_medio_es_solo_distancia_sobre_velocidad(): void
    {
        $this->assertEqualsWithDelta(400 / self::V, $this->segundos($this->colectivo(1, 600), 1, 1000), 0.001);
    }

    public function test_si_ya_esta_en_la_parada_la_llegada_es_cero(): void
    {
        $this->assertSame(0.0, $this->segundos($this->colectivo(1, 1000, 12, EstadoColectivo::EN_PARADA), 1, 1000));
        $this->assertSame(0.0, $this->segundos($this->colectivo(1, 1001.5), 1, 1000), 'A pocos metros se considera que llegó');
    }

    public function test_si_esta_detenido_en_una_parada_anterior_suma_lo_que_le_queda_de_espera(): void
    {
        // Detenido ahora, pero la velocidad que cuenta es la media reciente: venía a 5 m/s.
        $detenido = $this->colectivo(1, 1000, 9.0, EstadoColectivo::EN_PARADA);

        // Espera 9 s más, recorre 1000 m y no hay paradas entre medio.
        $this->assertEqualsWithDelta(9 + 1000 / self::V, $this->segundos($detenido, 1, 2000), 0.001);
    }

    public function test_si_acaba_de_pasar_la_parada_tiene_que_dar_la_vuelta_completa(): void
    {
        // Pasó la de 1000 m hace 10 m. Termina la ida (paradas: 2000), espera, hace la vuelta
        // (paradas: 2000 y 1000), espera y vuelve a salir hasta los 1000 m sin paradas en el medio.
        $esperado = (3000 - 1010) / self::V + 1 * self::PARADA + self::TERMINAL
            + 3000 / self::V + 2 * self::PARADA + self::TERMINAL
            + 1000 / self::V;

        $this->assertEqualsWithDelta($esperado, $this->segundos($this->colectivo(1, 1010), 1, 1000), 0.001);
        $this->assertGreaterThan(900, $esperado, 'Es más de un cuarto de hora');
    }

    public function test_si_viene_por_la_otra_mano_termina_ese_ramal_espera_y_sale_hacia_la_parada(): void
    {
        // En la vuelta, a 1000 m del inicio; la parada es la de 2000 m de la ida.
        $esperado = (3000 - 1000) / self::V + 1 * self::PARADA + self::TERMINAL   // le queda la parada de 2000 m de la vuelta
            + 2000 / self::V + 1 * self::PARADA;                                    // sale: parada de 1000 m, y llega a la de 2000

        $this->assertEqualsWithDelta($esperado, $this->segundos($this->colectivo(2, 1000), 1, 2000), 0.001);
    }

    public function test_si_esta_esperando_en_la_terminal_solo_cuenta_lo_que_le_falta_de_espera(): void
    {
        $enTerminal = $this->colectivo(2, 3000, 40.0, EstadoColectivo::EN_TERMINAL);

        // Termina su espera (40 s), cambia a la ida y recorre 1000 m sin paradas en el medio.
        $this->assertEqualsWithDelta(40 + 1000 / self::V, $this->segundos($enTerminal, 1, 1000), 0.001);
    }

    public function test_un_colectivo_que_viene_lento_tarda_mas(): void
    {
        $rapido = $this->segundos($this->colectivo(1, 500, v: 7.0), 1, 2000);
        $lento = $this->segundos($this->colectivo(1, 500, v: 2.5), 1, 2000);

        $this->assertGreaterThan($rapido * 2, $lento);
    }

    public function test_si_viene_casi_parado_no_se_dispara_a_infinito(): void
    {
        $segundos = $this->segundos($this->colectivo(1, 0, v: 0.0), 1, 1000);

        $this->assertEqualsWithDelta(1000 / Estimador::VELOCIDAD_MINIMA_MS, $segundos, 0.001);
        $this->assertTrue(is_finite($segundos));
    }

    public function test_con_una_falla_suma_el_arreglo_tipico_y_marca_la_cuenta_como_incierta(): void
    {
        $estimador = new Estimador($this->rutas());
        $normal = $estimador->llegada($this->colectivo(1, 500), 1, 1000);
        $averiado = $estimador->llegada($this->colectivo(1, 500, 0.0, EstadoColectivo::AVERIADO, 5.0, 'falla'), 1, 1000);

        $this->assertFalse($normal->incierta);
        $this->assertTrue($averiado->incierta);
        $this->assertEqualsWithDelta($normal->segundos + Estimador::ARREGLO_TIPICO_S, $averiado->segundos, 0.001);
    }

    public function test_un_colectivo_mas_cerca_siempre_llega_antes(): void
    {
        $anterior = INF;
        foreach ([100.0, 500.0, 900.0, 1100.0, 1700.0, 1998.0] as $posicion) {
            $segundos = $this->segundos($this->colectivo(1, $posicion), 1, 2000);
            $this->assertLessThan($anterior, $segundos, "En {$posicion} m");
            $anterior = $segundos;
        }
    }

    // ---------- desvíos (M7) ----------

    public function test_un_desvio_que_todavia_no_empezo_suma_el_tiempo_de_mas_del_camino_alternativo(): void
    {
        $normal = $this->colectivo(1, 500);
        $conDesvio = new Seguimiento(1, 1, 500.0, 0.0, EstadoColectivo::CIRCULANDO, self::V, 'desvio', 2);

        // El tramo de 1000 a 2000 m se hace por 1400 m, a 0,85 de la velocidad.
        $extra = 1400 / (Estimador::FACTOR_VELOCIDAD_EN_DESVIO * self::V) - 1000 / self::V;

        $this->assertEqualsWithDelta($extra, $this->segundos($conDesvio, 1, 3000) - $this->segundos($normal, 1, 3000), 0.5);
        $this->assertGreaterThan(100, $extra);
    }

    public function test_si_ya_esta_dentro_del_desvio_no_se_suma_nada_porque_la_velocidad_media_ya_lo_refleja(): void
    {
        $normal = $this->colectivo(1, 1500);
        $dentro = new Seguimiento(1, 1, 1500.0, 0.0, EstadoColectivo::FUERA_DE_RECORRIDO, self::V, 'desvio', 2);

        $this->assertSame($this->segundos($normal, 1, 3000), $this->segundos($dentro, 1, 3000));
    }

    public function test_una_parada_antes_del_tramo_desviado_no_se_ve_afectada(): void
    {
        $normal = $this->colectivo(1, 100);
        $conDesvio = new Seguimiento(1, 1, 100.0, 0.0, EstadoColectivo::CIRCULANDO, self::V, 'desvio', 2);

        $this->assertSame($this->segundos($normal, 1, 1000), $this->segundos($conDesvio, 1, 1000));
    }

    public function test_el_desvio_tambien_demora_a_quien_tiene_que_dar_la_vuelta_para_llegar_a_la_parada(): void
    {
        $normal = $this->colectivo(1, 500);
        $conDesvio = new Seguimiento(1, 1, 500.0, 0.0, EstadoColectivo::CIRCULANDO, self::V, 'desvio', 2);

        // La parada es del ramal contrario: igual tiene que terminar este ramal, pasando por el desvío.
        $this->assertGreaterThan($this->segundos($normal, 2, 1000) + 100, $this->segundos($conDesvio, 2, 1000));
    }

    public function test_con_desvio_la_estimacion_sigue_de_cerca_lo_que_hace_el_simulador(): void
    {
        $errores = [];

        foreach (range(1, 60) as $semilla) {
            $sim = new Simulador($semilla, $this->rutas());
            $estimador = new Estimador($this->rutas());

            $estado = new EstadoColectivo(1, 1, 300.0, 5.0, EstadoColectivo::CIRCULANDO, 0.0, 1, 6.0, 'desvio', 400.0, 2);

            $prediccion = $estimador->llegada(
                new Seguimiento(1, 1, $estado->distanciaM, 0.0, $estado->estado, 5.0, 'desvio', 2),
                1,
                3000.0,
            )->segundos;

            $real = 0.0;
            $tick = 0;
            while (! ($estado->ramalId === 1 && $estado->distanciaM >= 2997.0) && $real < 4000) {
                [$estado] = $sim->avanzar($estado, ++$tick, 2.0);
                $real += 2.0;
            }

            $errores[] = abs($prediccion - $real) / $real;
        }

        sort($errores);
        $mediana = $errores[intdiv(count($errores), 2)];

        $this->assertLessThan(0.25, $mediana, sprintf('Error mediano de %.1f %% con un desvío en el camino', $mediana * 100));
    }

    /**
     * La prueba que importa: se estima la llegada y después se deja correr el simulador de verdad
     * para ver cuánto tardó. Mide qué tan bien predice el estimador a lo que realmente va a pasar.
     */
    public function test_la_estimacion_coincide_con_lo_que_despues_hace_el_simulador(): void
    {
        $errores = [];
        $sesgo = [];

        foreach (range(1, 60) as $semilla) {
            $sim = new Simulador($semilla, $this->rutasSinDesvios());
            $estimador = new Estimador($this->rutasSinDesvios());

            foreach (range(1, 6) as $id) {
                $estado = $sim->estadoInicial($id, 1, $id - 1, 6);
                $media = $estado->velocidadCrucero * 0.85;

                // Un minuto de calentamiento para que la velocidad media refleje cómo viene andando.
                $tick = 0;
                for ($i = 0; $i < 30; $i++) {
                    [$estado] = $sim->avanzar($estado, ++$tick, 2.0);
                    if ($estado->velocidadMs >= 0.3) {
                        $media += ($estado->velocidadMs - $media) * (1 - exp(-2.0 / 60));
                    }
                }

                // Solo colectivos de la ida que todavía tienen la parada de 2000 m por delante.
                if ($estado->ramalId !== 1 || $estado->distanciaM > 1800 || $estado->incidente !== null) {
                    continue;
                }

                $prediccion = $estimador->llegada(
                    new Seguimiento($id, 1, $estado->distanciaM, $estado->esperaS, $estado->estado, $media),
                    1,
                    2000.0,
                )->segundos;

                $real = 0.0;
                $actual = $estado;
                while (! ($actual->ramalId === 1 && $actual->distanciaM >= 1997.0) && $real < 3000) {
                    [$actual] = $sim->avanzar($actual, ++$tick, 2.0);
                    $real += 2.0;
                }

                if ($real >= 3000 || $prediccion < 60) {
                    continue;
                }

                $errores[] = abs($prediccion - $real) / $real;
                $sesgo[] = ($prediccion - $real) / $real;
            }
        }

        $this->assertGreaterThan(50, count($errores), 'Hay muestras suficientes');

        sort($errores);
        $mediana = $errores[intdiv(count($errores), 2)];
        $promedioSesgo = array_sum($sesgo) / count($sesgo);

        $this->assertLessThan(0.20, $mediana, sprintf('Error mediano de %.1f %%', $mediana * 100));
        $this->assertEqualsWithDelta(0.0, $promedioSesgo, 0.10, sprintf('Sesgo de %.1f %%', $promedioSesgo * 100));
    }
}
