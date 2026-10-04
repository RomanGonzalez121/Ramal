<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_un_grado_de_latitud_son_unos_111_kilometros(): void
    {
        $metros = Geo::distanciaMetros([-60.0, -31.0], [-60.0, -30.0]);

        $this->assertEqualsWithDelta(111195, $metros, 100);
    }

    public function test_la_distancia_de_un_punto_a_si_mismo_es_cero(): void
    {
        $this->assertSame(0.0, Geo::distanciaMetros([-60.52, -31.73], [-60.52, -31.73]));
    }

    public function test_la_distancia_no_depende_del_orden(): void
    {
        $plaza = [-60.52978, -31.73301];
        $terminal = [-60.51790, -31.73955];

        $this->assertEqualsWithDelta(
            Geo::distanciaMetros($plaza, $terminal),
            Geo::distanciaMetros($terminal, $plaza),
            0.001,
        );
    }

    public function test_una_longitud_vale_menos_que_una_latitud_lejos_del_ecuador(): void
    {
        $este = Geo::distanciaMetros([-60.0, -31.7], [-59.0, -31.7]);
        $norte = Geo::distanciaMetros([-60.0, -31.7], [-60.0, -30.7]);

        $this->assertLessThan($norte, $este);
        $this->assertEqualsWithDelta($norte * cos(deg2rad(31.7)), $este, 300);
    }

    public function test_las_distancias_acumuladas_empiezan_en_cero_y_solo_crecen(): void
    {
        $puntos = [[-60.0, -31.0], [-60.0, -31.001], [-59.999, -31.001], [-59.999, -31.002]];

        $acumuladas = Geo::distanciasAcumuladas($puntos);

        $this->assertSame(0.0, $acumuladas[0]);
        $this->assertCount(4, $acumuladas);
        for ($i = 1; $i < count($acumuladas); $i++) {
            $this->assertGreaterThan($acumuladas[$i - 1], $acumuladas[$i]);
        }
    }

    public function test_un_punto_sobre_el_trazado_se_proyecta_a_distancia_cero(): void
    {
        $trazado = [[-60.0, -31.0], [-60.0, -31.01]];
        $acumuladas = Geo::distanciasAcumuladas($trazado);

        $r = Geo::proyectar([-60.0, -31.005], $trazado, $acumuladas);

        $this->assertEqualsWithDelta(0.0, $r['distancia_al_trazado_m'], 0.5);
        $this->assertEqualsWithDelta($acumuladas[1] / 2, $r['a_lo_largo_m'], 1.0);
    }

    public function test_un_punto_al_costado_mide_la_distancia_perpendicular(): void
    {
        $trazado = [[-60.0, -31.0], [-60.0, -31.01]];
        $acumuladas = Geo::distanciasAcumuladas($trazado);
        $costado = [-60.0 + 0.0005, -31.005]; // unos 47 m al este

        $r = Geo::proyectar($costado, $trazado, $acumuladas);

        $this->assertEqualsWithDelta(47, $r['distancia_al_trazado_m'], 3);
        $this->assertEqualsWithDelta($acumuladas[1] / 2, $r['a_lo_largo_m'], 1.0);
    }

    public function test_un_punto_pasado_del_final_se_proyecta_al_extremo(): void
    {
        $trazado = [[-60.0, -31.0], [-60.0, -31.01]];
        $acumuladas = Geo::distanciasAcumuladas($trazado);

        $r = Geo::proyectar([-60.0, -31.02], $trazado, $acumuladas);

        $this->assertEqualsWithDelta($acumuladas[1], $r['a_lo_largo_m'], 0.5);
        $this->assertGreaterThan(1000, $r['distancia_al_trazado_m']);
    }

    public function test_desde_obliga_a_buscar_mas_adelante_cuando_el_recorrido_repite_la_esquina(): void
    {
        // Ida por el eje y vuelta por el mismo eje: la esquina (-60, -31.005) se pisa dos veces.
        $trazado = [[-60.0, -31.0], [-60.0, -31.01], [-60.0, -31.0]];
        $acumuladas = Geo::distanciasAcumuladas($trazado);
        $punto = [-60.0, -31.005];

        $primera = Geo::proyectar($punto, $trazado, $acumuladas);
        $segunda = Geo::proyectar($punto, $trazado, $acumuladas, $acumuladas[1] + 1);

        $this->assertEqualsWithDelta($acumuladas[1] / 2, $primera['a_lo_largo_m'], 1.0);
        $this->assertGreaterThan($acumuladas[1], $segunda['a_lo_largo_m']);
        $this->assertEqualsWithDelta($acumuladas[1] * 1.5, $segunda['a_lo_largo_m'], 1.0);
    }
}
