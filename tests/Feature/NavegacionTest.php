<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CiudadSeeder::class);
    }

    public function test_la_barra_agrupa_los_destinos_por_quien_los_usa(): void
    {
        $this->get('/lineas')
            ->assertOk()
            ->assertSeeInOrder(['Mapa', 'Líneas', 'Cómo funciona', 'Para desarrolladores', 'Datos abiertos (API)', 'Identidad visual'])
            ->assertSee('Ingresar')
            ->assertSee('Menú');
    }

    public function test_marca_solo_la_pagina_actual(): void
    {
        $html = $this->get('/como-funciona')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*\/como-funciona"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/lineas"[^>]*aria-current="page"/', $html);
    }

    public function test_un_operador_ingresado_ve_centro_de_control_en_vez_de_ingresar(): void
    {
        $operador = User::factory()->create(['rol' => User::ROL_OPERADOR]);

        $this->actingAs($operador)->get('/lineas')->assertSee('Centro de control')->assertDontSee('Ingresar');
    }

    public function test_los_atajos_de_la_pagina_van_en_su_propia_fila(): void
    {
        $this->get('/como-funciona')->assertSee('aria-label="En esta página"', false)->assertSee('Qué es real');
        $this->get('/lineas')->assertSee('aria-label="En esta página"', false);
        $this->get('/api')->assertSee('aria-label="En esta página"', false);
    }

    public function test_detras_de_un_proxy_con_https_las_direcciones_salen_con_https(): void
    {
        // Es lo que pasa en Render: el visitante usa HTTPS pero el pedido llega al contenedor por HTTP con estas cabeceras.
        $html = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'ramal.example.com', 'X-Forwarded-Port' => '443'])
            ->get('/lineas')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="https://ramal.example.com/lineas"', $html);
        $this->assertStringNotContainsString('href="http://ramal.example.com', $html);
    }
}
