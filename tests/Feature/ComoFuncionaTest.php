<?php

namespace Tests\Feature;

use App\Estimaciones\Estimador;
use App\Historial\Instantanea;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComoFuncionaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
    }

    public function test_la_pagina_explica_lo_inventado_y_lo_real(): void
    {
        $this->withoutVite();

        $this->get('/como-funciona')
            ->assertOk()
            ->assertSee('Se simula el mundo, no la tecnología')
            ->assertSee('Inventado')
            ->assertSee('De verdad')
            ->assertSee('Los pasajeros')
            ->assertSee('Cómo se calcula cuándo llega')
            ->assertSee('De la base de datos al mapa')
            ->assertSee('Cómo se rebobina el día')
            ->assertSee('Lo que conviene saber');
    }

    public function test_los_numeros_salen_de_las_constantes_del_sistema(): void
    {
        $this->withoutVite();

        $pagina = $this->get('/como-funciona')->assertOk();

        // 5 líneas y 40 colectivos de la base; las esperas del estimador; el peso de una foto del historial.
        $pagina->assertSee('Las 5 líneas y sus horarios')->assertSee('Los 40 colectivos y sus choferes');
        $pagina->assertSee(str_replace('.', ',', (string) Estimador::PARADA_PROMEDIO_S).' s');
        $pagina->assertSee((40 * Instantanea::BYTES_POR_COLECTIVO).' B');
        $pagina->assertSee('por foto con 40 colectivos');
        $pagina->assertSee((string) config('ramal.historial.retencion_horas').' horas de historial');
    }

    public function test_el_peso_del_historial_retenido_es_coherente(): void
    {
        $this->withoutVite();

        // 600 B por foto, una cada 10 s, 48 horas: 600 * 360 * 48 = 10,368 MB.
        $this->get('/como-funciona')->assertSee('10,4 MB');
    }

    public function test_la_grilla_de_canales_tiene_las_celdas_de_la_configuracion(): void
    {
        $this->withoutVite();

        $pagina = $this->get('/como-funciona')->assertOk();

        $pagina->assertSee('La ciudad se divide en 16 celdas');
        $pagina->assertSee('aria-label="Celda 16"', false);
        $pagina->assertDontSee('aria-label="Celda 17"', false);
    }

    public function test_las_otras_paginas_enlazan_a_la_explicacion(): void
    {
        $this->withoutVite();

        $this->get('/')->assertSee(route('como-funciona'), false);
        $this->get('/lineas')->assertSee(route('como-funciona'), false);
    }
}
