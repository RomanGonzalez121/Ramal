<?php

namespace Tests\Feature;

use App\Support\Contraste;
use Tests\TestCase;

class IdentidadPaginaTest extends TestCase
{
    public function test_la_raiz_es_el_mapa_publico(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Paraná,', 'ahora'])
            ->assertSee('OpenStreetMap');
    }

    public function test_la_pagina_de_identidad_se_muestra_con_sus_secciones(): void
    {
        $this->withoutVite();

        $this->get('/identidad')
            ->assertOk()
            ->assertSee('Colectivos de Paraná, en vivo')
            ->assertSee('Veinte íconos propios', false)
            ->assertSee('Piezas propias')
            ->assertSee('9,6:1');
    }

    public function test_los_colores_de_la_tabla_coinciden_con_los_tokens_del_css(): void
    {
        $css = strtoupper(file_get_contents(resource_path('css/app.css')));

        foreach (config('identidad.colores') as $color) {
            foreach (['dia', 'noche'] as $tema) {
                if ($color[$tema]) {
                    $this->assertStringContainsString(
                        strtoupper($color[$tema]),
                        $css,
                        "{$color['nombre']} ({$tema}) no está en app.css",
                    );
                }
            }
        }
    }

    public function test_los_colores_de_linea_cumplen_el_contraste_minimo_de_texto_normal(): void
    {
        foreach (config('identidad.colores') as $color) {
            if (! isset($color['linea'])) {
                continue;
            }

            $this->assertGreaterThanOrEqual(
                4.5,
                Contraste::ratio($color['dia'], config('identidad.papel')),
                "{$color['nombre']} en Día",
            );
            $this->assertGreaterThanOrEqual(
                4.5,
                Contraste::ratio($color['noche'], config('identidad.asfalto')),
                "{$color['nombre']} en Noche",
            );
        }
    }

    public function test_el_texto_blanco_sobre_cada_linea_en_dia_y_asfalto_sobre_cada_linea_en_noche_se_leen(): void
    {
        foreach (config('identidad.colores') as $color) {
            if (! isset($color['linea'])) {
                continue;
            }

            $this->assertGreaterThanOrEqual(4.5, Contraste::ratio('#FFFFFF', $color['dia']), "Número sobre {$color['nombre']} en Día");
            $this->assertGreaterThanOrEqual(4.5, Contraste::ratio(config('identidad.asfalto'), $color['noche']), "Número sobre {$color['nombre']} en Noche");
        }
    }
}
