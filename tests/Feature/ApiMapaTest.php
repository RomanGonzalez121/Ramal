<?php

namespace Tests\Feature;

use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMapaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);
        $servicio = new ServicioSimulacion;
        $servicio->preparar();
        $servicio->tick(2.0);
    }

    public function test_la_api_del_mapa_devuelve_lineas_paradas_y_recorridos(): void
    {
        $respuesta = $this->getJson('/api/mapa')->assertOk();

        $respuesta->assertJsonCount(5, 'lineas')
            ->assertJsonCount(11, 'paradas')
            ->assertJsonCount(40, 'colectivos')
            ->assertJsonStructure(['colectivos' => [['id', 'interno', 'linea']]])
            ->assertJsonPath('celdas.columnas', 4)
            ->assertJsonPath('lineas.0.numero', 1)
            ->assertJsonCount(2, 'lineas.0.ramales');

        $this->assertGreaterThan(30, count($respuesta->json('lineas.0.ramales.0.recorrido')));
        $this->assertStringContainsString('max-age', $respuesta->headers->get('Cache-Control'));
    }

    public function test_las_posiciones_sin_vista_traen_a_los_cuarenta(): void
    {
        $this->getJson('/api/posiciones')
            ->assertOk()
            ->assertJsonCount(40, 'colectivos')
            ->assertJsonPath('tick', 1)
            ->assertJsonCount(16, 'celdas');
    }

    public function test_con_una_vista_chica_solo_vienen_los_colectivos_de_esas_celdas(): void
    {
        $todos = $this->getJson('/api/posiciones')->json('colectivos');
        $recorte = $this->getJson('/api/posiciones?vista=-60.54,-31.74,-60.51,-31.72')->assertOk();

        $this->assertLessThan(count($todos), count($recorte->json('colectivos')));
        $this->assertLessThan(16, count($recorte->json('celdas')));

        foreach ($recorte->json('colectivos') as $c) {
            $this->assertTrue($c['salto'], 'La foto inicial siempre es un punto, sin trazado');
            $this->assertCount(1, $c['ruta']);
        }
    }

    public function test_una_vista_mal_armada_se_rechaza_con_un_mensaje(): void
    {
        $this->getJson('/api/posiciones?vista=1,2,3')
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La vista tiene que ser oeste,sur,este,norte.');
    }
}
