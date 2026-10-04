<?php

namespace Tests\Unit\Simulacion;

use App\Simulacion\EstadoColectivo;
use App\Simulacion\Simulador;
use PHPUnit\Framework\TestCase;

/**
 * M7: un desvío manda al colectivo por otras calles entre dos paradas. En las rutas de prueba cada tramo de
 * 1000 m tiene un camino alternativo de 1400 m que se separa 200 m de la avenida.
 */
class DesviosTest extends TestCase
{
    use RutasDePrueba;

    private const DT = 2.0;

    /** Un colectivo entre la parada 1 y la 2 que va a hacer el desvío del tramo 2 -> 3 (de 1000 a 2000 m). */
    private function conDesvio(): EstadoColectivo
    {
        return new EstadoColectivo(
            colectivoId: 1,
            ramalId: 1,
            distanciaM: 300.0,
            velocidadMs: 5.0,
            estado: EstadoColectivo::CIRCULANDO,
            esperaS: 0.0,
            ultimaParada: 1,
            velocidadCrucero: 6.0,
            incidente: 'desvio',
            incidenteRestanteS: 400.0,
            desvioOrden: 2,
        );
    }

    public function test_el_camino_alternativo_es_mas_largo_y_cada_metro_real_vale_menos_metros_del_recorrido(): void
    {
        $ruta = $this->rutas()[1];

        $this->assertEqualsWithDelta(1000 / 1400, $ruta->escalaDesvio(2), 0.01);
        $this->assertEqualsWithDelta($ruta->escalaDesvio(2), $ruta->escalaEn(1500.0, 2), 1e-9);
        $this->assertSame(1.0, $ruta->escalaEn(1500.0, null), 'Sin desvío en curso, la escala es 1');
        $this->assertSame(1.0, $ruta->escalaEn(500.0, 2), 'Antes de llegar al tramo desviado, la escala es 1');
        $this->assertSame(1.0, $ruta->escalaEn(2500.0, 2), 'Pasado el tramo desviado, la escala es 1');
    }

    public function test_dentro_del_desvio_la_posicion_sale_del_camino_alternativo(): void
    {
        $ruta = $this->rutas()[1];

        [, $latitudNormal] = $ruta->punto(1500.0);
        [, $latitudDesvio] = $ruta->punto(1500.0, 2);

        $this->assertEqualsWithDelta(self::LATITUD, $latitudNormal, 1e-9);
        $this->assertGreaterThan(self::LATITUD + 0.0015, $latitudDesvio, 'A mitad del desvío está unos 200 m al norte');
    }

    public function test_en_las_paradas_de_salida_y_de_vuelta_el_desvio_coincide_con_el_recorrido(): void
    {
        $ruta = $this->rutas()[1];

        foreach ([1000.0, 2000.0] as $metros) {
            $normal = $ruta->punto($metros);
            $conDesvio = $ruta->punto($metros, 2);

            $this->assertEqualsWithDelta($normal[0], $conDesvio[0], 1e-9);
            $this->assertEqualsWithDelta($normal[1], $conDesvio[1], 1e-9);
        }
    }

    public function test_el_tramo_dentro_del_desvio_pasa_por_las_esquinas_del_camino_alternativo(): void
    {
        $ruta = $this->rutas()[1];

        $normal = $ruta->tramo(1000.0, 2000.0);
        $desviado = $ruta->tramo(1000.0, 2000.0, 2);

        $this->assertSame(11, count($normal), 'Por la avenida: extremos y 9 puntos intermedios');
        $this->assertSame(4, count($desviado), 'Por el desvío: extremos y las 2 esquinas');
        $this->assertEqualsWithDelta(self::LATITUD + self::SALTO_LATITUD, $desviado[1][1], 1e-9);
        $this->assertEqualsWithDelta(self::LATITUD + self::SALTO_LATITUD, $desviado[2][1], 1e-9);
    }

    public function test_un_tramo_que_entra_al_desvio_a_mitad_de_camino_empieza_por_la_avenida_y_termina_en_el_desvio(): void
    {
        $ruta = $this->rutas()[1];

        $tramo = $ruta->tramo(900.0, 1100.0, 2);

        $this->assertEqualsWithDelta(self::LATITUD, $tramo[0][1], 1e-9);
        $this->assertGreaterThan(self::LATITUD + 0.0005, $tramo[count($tramo) - 1][1]);
    }

    public function test_sin_desvio_en_curso_el_trazado_es_el_de_siempre(): void
    {
        $ruta = $this->rutas()[1];

        $this->assertEquals($ruta->tramo(1000.0, 2000.0), $ruta->tramo(1000.0, 2000.0, null));
    }

    public function test_el_colectivo_hace_el_desvio_tarda_mas_y_al_volver_al_recorrido_se_resuelve(): void
    {
        $sim = new Simulador(21, $this->rutas());
        $sinDesvio = new Simulador(21, $this->rutasSinDesvios());
        $ruta = $this->rutas()[1];

        $estado = $this->conDesvio();
        $normal = $estado->con(['incidente' => null, 'desvioOrden' => null, 'incidenteRestanteS' => 0.0]);

        $fuera = 0;
        $resueltos = [];
        $tiempoDesvio = null;
        $tiempoNormal = null;
        $tNormal = null;
        $maxLatitud = null;

        for ($t = 1; $t <= 3000; $t++) {
            if ($estado->ultimaParada < 3) {
                [$estado, $eventos] = $sim->avanzar($estado, $t, self::DT);
                foreach ($eventos as $e) {
                    if ($e['tipo'] === 'resuelto') {
                        $resueltos[] = $e['incidente'];
                    }
                }

                if ($estado->estado === EstadoColectivo::FUERA_DE_RECORRIDO) {
                    $fuera++;
                    // Mientras está desviado, está en el camino alternativo (al norte), nunca al sur de la avenida.
                    $latitud = $ruta->punto($estado->distanciaM, $estado->desvioOrden)[1];
                    $this->assertGreaterThanOrEqual(self::LATITUD - 1e-9, $latitud);
                    $maxLatitud = max($maxLatitud ?? $latitud, $latitud);
                }

                if ($estado->ultimaParada >= 3 && $tiempoDesvio === null) {
                    $tiempoDesvio = $t * self::DT;
                }
            }

            if ($normal->ultimaParada < 3) {
                [$normal] = $sinDesvio->avanzar($normal, $t, self::DT);
                if ($normal->ultimaParada >= 3 && $tiempoNormal === null) {
                    $tiempoNormal = $t * self::DT;
                }
            }

            if ($tiempoDesvio !== null && $tiempoNormal !== null) {
                break;
            }
        }

        $this->assertNotNull($tiempoDesvio);
        $this->assertGreaterThan(10, $fuera, 'Pasó un buen rato fuera de recorrido');
        $this->assertGreaterThan(self::LATITUD + 0.0015, $maxLatitud, 'En algún momento estuvo en la parte más alejada del camino alternativo');
        $this->assertSame(['desvio'], $resueltos, 'Se resolvió una sola vez, al volver al recorrido');
        $this->assertNull($estado->incidente);
        $this->assertNull($estado->desvioOrden);
        $this->assertGreaterThan($tiempoNormal * 1.2, $tiempoDesvio, "Por el desvío ({$tiempoDesvio} s) tarda bastante más que por el recorrido ({$tiempoNormal} s)");
    }

    public function test_la_distancia_del_recorrido_nunca_retrocede_durante_el_desvio(): void
    {
        $sim = new Simulador(21, $this->rutas());
        $estado = $this->conDesvio();
        $anterior = $estado->distanciaM;

        for ($t = 1; $t <= 2000 && $estado->ultimaParada < 3; $t++) {
            [$estado] = $sim->avanzar($estado, $t, self::DT);
            $this->assertGreaterThanOrEqual($anterior, $estado->distanciaM);
            $anterior = $estado->distanciaM;
        }

        $this->assertSame(3, $estado->ultimaParada);
        $this->assertEqualsWithDelta(2000.0, $estado->distanciaM, 0.01, 'Llegó justo a la parada donde el desvío vuelve al recorrido');
    }

    public function test_antes_de_entrar_al_camino_alternativo_todavia_circula_normal(): void
    {
        $sim = new Simulador(21, $this->rutas());

        [$nuevo] = $sim->avanzar($this->conDesvio(), 1, self::DT);

        $this->assertSame(EstadoColectivo::CIRCULANDO, $nuevo->estado);
        $this->assertSame('desvio', $nuevo->incidente);
    }

    public function test_el_desvio_no_se_acaba_por_el_paso_del_tiempo(): void
    {
        $sim = new Simulador(21, $this->rutas());
        $parado = $this->conDesvio()->con(['incidenteRestanteS' => 0.0, 'esperaS' => 100000.0, 'estado' => EstadoColectivo::EN_PARADA]);

        [$nuevo, $eventos] = $sim->avanzar($parado, 1, self::DT);

        $this->assertSame('desvio', $nuevo->incidente);
        $this->assertSame([], $eventos);
    }

    public function test_sin_camino_alternativo_en_ningun_tramo_no_hay_desvios(): void
    {
        $sim = new Simulador(31, $this->rutasSinDesvios());
        $tipos = [];

        foreach (range(1, 20) as $id) {
            $estado = $sim->estadoInicial($id, 1, $id % 8, 8);
            for ($t = 1; $t <= 9000; $t++) {
                [$estado, $eventos] = $sim->avanzar($estado, $t, self::DT);
                foreach ($eventos as $e) {
                    $tipos[$e['incidente']] = true;
                }
            }
        }

        $this->assertArrayNotHasKey('desvio', $tipos);
        $this->assertArrayHasKey('demora', $tipos);
    }

    public function test_con_caminos_alternativos_los_desvios_aparecen_en_la_simulacion(): void
    {
        $sim = new Simulador(31, $this->rutas());
        $desvios = 0;
        $resueltos = 0;

        foreach (range(1, 20) as $id) {
            $estado = $sim->estadoInicial($id, 1, $id % 8, 8);
            for ($t = 1; $t <= 9000; $t++) {
                [$estado, $eventos] = $sim->avanzar($estado, $t, self::DT);
                foreach ($eventos as $e) {
                    if ($e['incidente'] === 'desvio') {
                        $e['tipo'] === 'nuevo' ? $desvios++ : $resueltos++;
                    }
                }
            }
        }

        $this->assertGreaterThan(3, $desvios);
        $this->assertGreaterThanOrEqual($desvios - 20, $resueltos, 'Casi todos los desvíos se terminaron (los que seguían abiertos al final no cuentan)');
    }

    public function test_atender_acorta_una_falla_y_una_demora(): void
    {
        $sim = new Simulador(1, $this->rutas());
        $falla = $this->conDesvio()->con(['incidente' => 'falla', 'incidenteRestanteS' => 500.0, 'desvioOrden' => null]);
        $demora = $falla->con(['incidente' => 'demora', 'incidenteRestanteS' => 300.0]);

        [$a, $resueltoA] = $sim->aplicarOrden($falla, 'atender');
        [$b] = $sim->aplicarOrden($demora, 'atender');

        $this->assertFalse($resueltoA);
        $this->assertSame(120.0, $a->incidenteRestanteS);
        $this->assertSame(60.0, $b->incidenteRestanteS);
        $this->assertSame('falla', $a->incidente, 'Atender no lo resuelve, lo acorta');
    }

    public function test_atender_nunca_alarga_un_incidente_que_ya_estaba_por_terminar(): void
    {
        $sim = new Simulador(1, $this->rutas());
        $casiListo = $this->conDesvio()->con(['incidente' => 'falla', 'incidenteRestanteS' => 30.0, 'desvioOrden' => null]);

        [$nuevo] = $sim->aplicarOrden($casiListo, 'atender');

        $this->assertSame(30.0, $nuevo->incidenteRestanteS);
    }

    public function test_resolver_termina_la_falla_o_la_demora_en_el_acto(): void
    {
        $sim = new Simulador(1, $this->rutas());
        $falla = $this->conDesvio()->con(['incidente' => 'falla', 'incidenteRestanteS' => 500.0, 'desvioOrden' => null]);

        [$nuevo, $resuelto] = $sim->aplicarOrden($falla, 'resolver');

        $this->assertTrue($resuelto);
        $this->assertNull($nuevo->incidente);
        $this->assertSame(0.0, $nuevo->incidenteRestanteS);
    }

    public function test_un_desvio_no_se_puede_dar_por_resuelto_a_mano(): void
    {
        $sim = new Simulador(1, $this->rutas());
        $desviado = $this->conDesvio();

        [$resolver, $resuelto] = $sim->aplicarOrden($desviado, 'resolver');
        [$atender] = $sim->aplicarOrden($desviado, 'atender');

        $this->assertFalse($resuelto);
        $this->assertSame($desviado, $resolver);
        $this->assertSame($desviado, $atender);
    }

    public function test_dar_una_orden_a_un_colectivo_sin_incidente_no_hace_nada(): void
    {
        $sim = new Simulador(1, $this->rutas());
        $sano = $this->conDesvio()->con(['incidente' => null, 'desvioOrden' => null]);

        [$nuevo, $resuelto] = $sim->aplicarOrden($sano, 'resolver');

        $this->assertFalse($resuelto);
        $this->assertSame($sano, $nuevo);
    }
}
