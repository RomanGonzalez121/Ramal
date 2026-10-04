<?php

namespace Tests\Feature;

use App\Models\Chofer;
use App\Models\Colectivo;
use App\Models\Horario;
use App\Models\Linea;
use App\Models\Parada;
use App\Models\Ramal;
use App\Support\Geo;
use Database\Seeders\CiudadSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CiudadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
    }

    public function test_el_seeder_deja_cinco_lineas_con_dos_ramales_cada_una(): void
    {
        $this->assertSame(5, Linea::count());
        $this->assertSame(10, Ramal::count());

        foreach (Linea::with('ramales')->get() as $linea) {
            $this->assertEqualsCanonicalizing(['ida', 'vuelta'], $linea->ramales->pluck('sentido')->all(), "Línea {$linea->numero}");
        }
    }

    public function test_hay_cuarenta_colectivos_ocho_por_linea_cada_uno_con_su_chofer(): void
    {
        $this->assertSame(40, Colectivo::count());
        $this->assertSame(40, Chofer::count());

        foreach (Linea::withCount('colectivos')->get() as $linea) {
            $this->assertSame(8, $linea->colectivos_count);
        }

        $this->assertSame(0, Colectivo::whereNull('chofer_id')->count());
        $this->assertSame(40, Colectivo::distinct('interno')->count('interno'));
    }

    public function test_los_nombres_de_los_choferes_son_distintos_y_siempre_los_mismos(): void
    {
        $nombres = Chofer::orderBy('legajo')->pluck('nombre')->all();

        $this->assertCount(40, array_unique($nombres), 'Ningún nombre se repite');
        $this->assertSame('Marcelo Gómez', $nombres[0]);
        $this->assertSame('Silvia Acosta', $nombres[1]);
    }

    public function test_cada_linea_tiene_horario_para_los_tres_tipos_de_dia(): void
    {
        $this->assertSame(15, Horario::count());

        foreach (Linea::with('horarios')->get() as $linea) {
            $this->assertEqualsCanonicalizing(['habil', 'sabado', 'domingo'], $linea->horarios->pluck('dia')->all());
        }
    }

    public function test_cada_ramal_tiene_un_recorrido_de_largo_razonable(): void
    {
        foreach (Ramal::with('recorrido', 'linea')->get() as $ramal) {
            $etiqueta = "Línea {$ramal->linea->numero} {$ramal->sentido}";

            $this->assertNotNull($ramal->recorrido, $etiqueta);
            $this->assertGreaterThan(3000, $ramal->recorrido->largo_m, $etiqueta);
            $this->assertLessThan(15000, $ramal->recorrido->largo_m, $etiqueta);
            $this->assertGreaterThan(30, count($ramal->recorrido->puntos), $etiqueta);
        }
    }

    public function test_las_distancias_acumuladas_del_recorrido_empiezan_en_cero_y_nunca_bajan(): void
    {
        foreach (Ramal::with('recorrido')->get() as $ramal) {
            $d = $ramal->recorrido->distancias;

            $this->assertCount(count($ramal->recorrido->puntos), $d);
            $this->assertSame(0, (int) $d[0]);

            for ($i = 1; $i < count($d); $i++) {
                $this->assertGreaterThanOrEqual($d[$i - 1], $d[$i]);
            }

            $this->assertEqualsWithDelta(end($d), $ramal->recorrido->largo_m, 1);
        }
    }

    public function test_los_puntos_del_recorrido_estan_dentro_de_parana(): void
    {
        foreach (Ramal::with('recorrido')->get() as $ramal) {
            foreach ($ramal->recorrido->puntos as [$longitud, $latitud]) {
                $this->assertGreaterThan(-60.62, $longitud);
                $this->assertLessThan(-60.42, $longitud);
                $this->assertGreaterThan(-31.82, $latitud);
                $this->assertLessThan(-31.66, $latitud);
            }
        }
    }

    public function test_las_paradas_de_cada_ramal_caen_sobre_su_trazado_y_en_orden(): void
    {
        foreach (Ramal::with('recorrido', 'paradas', 'linea')->get() as $ramal) {
            $etiqueta = "Línea {$ramal->linea->numero} {$ramal->sentido}";
            $anterior = -1;

            $this->assertGreaterThanOrEqual(3, $ramal->paradas->count(), $etiqueta);

            foreach ($ramal->paradas as $posicion => $parada) {
                $proyeccion = Geo::proyectar(
                    [$parada->longitud, $parada->latitud],
                    $ramal->recorrido->puntos,
                    $ramal->recorrido->distancias,
                );

                $this->assertLessThan(10, $proyeccion['distancia_al_trazado_m'], "{$etiqueta}: {$parada->nombre} está lejos de la calle");
                $this->assertSame($posicion + 1, $parada->pivot->orden, $etiqueta);
                $this->assertGreaterThan($anterior, $parada->pivot->distancia_m, "{$etiqueta}: {$parada->nombre} no va después de la anterior");
                $anterior = $parada->pivot->distancia_m;
            }
        }
    }

    public function test_la_ida_empieza_donde_termina_la_vuelta(): void
    {
        foreach (Linea::with('ramales.paradas')->get() as $linea) {
            $ida = $linea->ramales->firstWhere('sentido', 'ida')->paradas;
            $vuelta = $linea->ramales->firstWhere('sentido', 'vuelta')->paradas;

            $this->assertSame($ida->first()->id, $vuelta->last()->id, "Línea {$linea->numero}");
            $this->assertSame($ida->last()->id, $vuelta->first()->id, "Línea {$linea->numero}");
        }
    }

    public function test_el_destino_de_cada_ramal_es_su_ultima_parada(): void
    {
        foreach (Ramal::with('paradas')->get() as $ramal) {
            $this->assertSame($ramal->paradas->last()->nombre, $ramal->destino);
        }
    }

    public function test_hay_paradas_compartidas_entre_lineas(): void
    {
        $plaza = Parada::where('nombre', 'Plaza 1° de Mayo')->firstOrFail();

        $this->assertGreaterThanOrEqual(4, $plaza->ramales()->distinct('linea_id')->count('linea_id'));
    }

    public function test_dos_lineas_no_pueden_tener_el_mismo_numero(): void
    {
        $this->expectException(QueryException::class);

        Linea::create(['numero' => 1, 'nombre' => 'Repetida', 'destino' => 'Ninguno']);
    }

    public function test_borrar_una_linea_borra_sus_ramales_recorridos_colectivos_y_horarios(): void
    {
        Linea::where('numero', 3)->firstOrFail()->delete();

        $this->assertSame(4, Linea::count());
        $this->assertSame(8, Ramal::count());
        $this->assertSame(32, Colectivo::count());
        $this->assertSame(12, Horario::count());
    }
}
