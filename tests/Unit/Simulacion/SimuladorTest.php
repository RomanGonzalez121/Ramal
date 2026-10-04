<?php

namespace Tests\Unit\Simulacion;

use App\Simulacion\Azar;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\Simulador;
use PHPUnit\Framework\TestCase;

class SimuladorTest extends TestCase
{
    use RutasDePrueba;

    private const DT = 2.0;

    /** @return array<int, EstadoColectivo> estados a lo largo de `$ticks`, empezando por el inicial */
    private function correr(Simulador $sim, EstadoColectivo $inicio, int $ticks, ?array &$eventos = null): array
    {
        $estados = [$inicio];
        $eventos = [];
        $actual = $inicio;

        for ($t = 1; $t <= $ticks; $t++) {
            [$actual, $nuevos] = $sim->avanzar($actual, $t, self::DT);
            $estados[] = $actual;
            foreach ($nuevos as $e) {
                $eventos[] = $e + ['tick' => $t];
            }
        }

        return $estados;
    }

    public function test_misma_semilla_da_exactamente_la_misma_corrida(): void
    {
        $a = new Simulador(777, $this->rutas());
        $b = new Simulador(777, $this->rutas());

        $x = $this->correr($a, $a->estadoInicial(5, 1, 2, 8), 1500);
        $y = $this->correr($b, $b->estadoInicial(5, 1, 2, 8), 1500);

        $this->assertEquals($x, $y);
    }

    public function test_otra_semilla_da_otra_corrida(): void
    {
        $a = new Simulador(777, $this->rutas());
        $b = new Simulador(778, $this->rutas());

        $x = $this->correr($a, $a->estadoInicial(5, 1, 2, 8), 300);
        $y = $this->correr($b, $b->estadoInicial(5, 1, 2, 8), 300);

        $this->assertNotEquals(end($x)->distanciaM, end($y)->distanciaM);
    }

    public function test_el_azar_es_reproducible_y_cae_entre_cero_y_uno(): void
    {
        $this->assertSame(Azar::flotante(1, 2, 3, 4), Azar::flotante(1, 2, 3, 4));
        $this->assertNotSame(Azar::flotante(1, 2, 3, 4), Azar::flotante(1, 2, 3, 5));

        $suma = 0.0;
        for ($i = 0; $i < 20000; $i++) {
            $n = Azar::flotante(42, 7, $i);
            $this->assertGreaterThanOrEqual(0.0, $n);
            $this->assertLessThan(1.0, $n);
            $suma += $n;
        }

        $this->assertEqualsWithDelta(0.5, $suma / 20000, 0.01);
    }

    public function test_el_azar_no_se_rompe_con_ticks_enormes(): void
    {
        $n = Azar::flotante(PHP_INT_MAX >> 33, 40, 4_000_000_000, 3);

        $this->assertGreaterThanOrEqual(0.0, $n);
        $this->assertLessThan(1.0, $n);
    }

    public function test_el_colectivo_nunca_retrocede_ni_se_sale_del_ramal(): void
    {
        $sim = new Simulador(11, $this->rutas());
        $estados = $this->correr($sim, $sim->estadoEnTerminal(1, 1, 0.0), 3000);

        for ($i = 1; $i < count($estados); $i++) {
            if ($estados[$i]->ramalId !== $estados[$i - 1]->ramalId) {
                $this->assertLessThan(1.0, $estados[$i]->distanciaM, 'Al cambiar de sentido arranca del principio');

                continue;
            }

            $this->assertGreaterThanOrEqual($estados[$i - 1]->distanciaM, $estados[$i]->distanciaM);
            $this->assertLessThanOrEqual(3000.0, $estados[$i]->distanciaM);
        }
    }

    public function test_se_detiene_en_cada_parada_del_recorrido(): void
    {
        $sim = new Simulador(11, $this->rutas());
        $estados = $this->correr($sim, $sim->estadoEnTerminal(1, 1, 0.0), 2000);

        foreach ([1000.0, 2000.0, 3000.0] as $parada) {
            $detenidos = array_filter($estados, fn (EstadoColectivo $e) => $e->ramalId === 1
                && abs($e->distanciaM - $parada) < 0.01
                && $e->velocidadMs === 0.0
                && in_array($e->estado, [EstadoColectivo::EN_PARADA, EstadoColectivo::EN_TERMINAL], true));

            $this->assertNotEmpty($detenidos, "No se detuvo en la parada de {$parada} m");
        }
    }

    public function test_la_velocidad_cambia_de_a_poco_y_nunca_es_negativa(): void
    {
        $sim = new Simulador(11, $this->rutas());
        $estados = $this->correr($sim, $sim->estadoInicial(3, 1, 0, 4), 2000);

        for ($i = 1; $i < count($estados); $i++) {
            $this->assertGreaterThanOrEqual(0.0, $estados[$i]->velocidadMs);
            if ($estados[$i - 1]->esperaS <= 0 && $estados[$i]->esperaS <= 0) {
                $this->assertLessThanOrEqual(Simulador::FRENADO * self::DT + 1e-6, abs($estados[$i]->velocidadMs - $estados[$i - 1]->velocidadMs));
            }
        }
    }

    public function test_en_la_terminal_espera_y_sale_en_sentido_contrario(): void
    {
        $sim = new Simulador(11, $this->rutas());
        $estados = $this->correr($sim, $sim->estadoEnTerminal(1, 1, 0.0), 2000);

        $enTerminal = array_values(array_filter($estados, fn ($e) => $e->estado === EstadoColectivo::EN_TERMINAL));
        $this->assertGreaterThan(20, count($enTerminal), 'Espera más de un minuto en la terminal');
        $this->assertTrue(
            (bool) array_filter($estados, fn ($e) => $e->ramalId === 2 && $e->estado === EstadoColectivo::CIRCULANDO),
            'Sale por el ramal de vuelta',
        );
    }

    public function test_los_incidentes_empiezan_terminan_y_la_falla_detiene_al_colectivo(): void
    {
        $sim = new Simulador(31, $this->rutas());
        $tipos = [];

        // 20 colectivos durante 5 horas: sobran incidentes de todos los tipos. Se revisa tick a tick, sin guardar todo.
        foreach (range(1, 20) as $id) {
            $estado = $sim->estadoInicial($id, 1, $id % 8, 8);
            $abierto = false;

            for ($t = 1; $t <= 9000; $t++) {
                [$estado, $eventos] = $sim->avanzar($estado, $t, self::DT);

                foreach ($eventos as $evento) {
                    if ($evento['tipo'] === 'nuevo') {
                        $this->assertFalse($abierto, 'Empezó un incidente sin resolver el anterior');
                        $abierto = true;
                        $tipos[$evento['incidente']] = true;
                    } else {
                        $this->assertTrue($abierto, 'Se resolvió un incidente que no había empezado');
                        $abierto = false;
                    }
                }

                if ($estado->incidente === 'falla' && $estado->esperaS <= 0) {
                    $this->assertSame(0.0, $estado->velocidadMs);
                }
            }
        }

        $this->assertEqualsCanonicalizing(['demora', 'desvio', 'falla'], array_keys($tipos));
    }

    public function test_un_colectivo_demorado_va_mas_lento_que_uno_normal(): void
    {
        $sim = new Simulador(5, $this->rutas());
        $normal = $sim->estadoInicial(9, 1, 0, 4)->con(['distanciaM' => 1100.0, 'ultimaParada' => 2]);
        $demorado = $normal->con(['incidente' => 'demora', 'incidenteRestanteS' => 10_000.0]);

        $a = $normal;
        $b = $demorado;
        for ($t = 1; $t <= 40; $t++) {
            [$a] = $sim->avanzar($a, $t, self::DT);
            [$b] = $sim->avanzar($b, $t, self::DT);
        }

        $this->assertLessThan($a->distanciaM, $b->distanciaM);
        $this->assertSame(EstadoColectivo::DEMORADO, $b->estado);
    }

    public function test_los_colectivos_arrancan_repartidos_a_lo_largo_del_ciclo(): void
    {
        $sim = new Simulador(5, $this->rutas());
        $posiciones = [];

        foreach (range(0, 7) as $i) {
            $e = $sim->estadoInicial($i + 1, 1, $i, 8);
            $posiciones[] = ($e->ramalId === 1 ? 0 : 3000) + $e->distanciaM;
        }

        sort($posiciones);
        for ($i = 1; $i < count($posiciones); $i++) {
            $this->assertGreaterThan(300, $posiciones[$i] - $posiciones[$i - 1], 'Quedaron amontonados');
        }
    }

    public function test_el_tramo_incluye_los_vertices_intermedios(): void
    {
        $ruta = $this->rutas()[1];

        $tramo = $ruta->tramo(150.0, 450.0);

        $this->assertCount(5, $tramo); // extremo, tres vértices (200, 300, 400) y extremo
        $this->assertEqualsWithDelta($ruta->puntos[2][0], $tramo[1][0], 1e-9);
    }

    public function test_el_rumbo_hacia_el_este_es_cero_y_hacia_el_oeste_es_180(): void
    {
        $rutas = $this->rutas();

        $this->assertEqualsWithDelta(0.0, $rutas[1]->punto(500.0)[2], 0.01);
        $this->assertEqualsWithDelta(180.0, abs($rutas[2]->punto(500.0)[2]), 0.01);
    }
}
