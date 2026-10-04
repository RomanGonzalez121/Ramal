<?php

namespace Tests\Feature;

use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineasPaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_lineas_muestra_las_cinco_lineas_con_sus_paradas(): void
    {
        $this->withoutVite();
        $this->seed(CiudadSeeder::class);

        $this->get('/lineas')
            ->assertOk()
            ->assertSee('Cinco líneas, calles reales')
            ->assertSee('Plaza 1° de Mayo')
            ->assertSee('Terminal de Ómnibus')
            ->assertSee('Parque Gazzano')
            ->assertSee('Costanera')
            ->assertSee('cada 12 min');
    }

    public function test_el_mapa_dibuja_un_trazo_por_ramal(): void
    {
        $this->withoutVite();
        $this->seed(CiudadSeeder::class);

        $html = $this->get('/lineas')->getContent();

        $this->assertSame(10, substr_count($html, 'stroke-linejoin="round" stroke-linecap="round"'));
    }
}
