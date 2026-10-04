<?php

namespace Tests\Feature;

use Tests\TestCase;

class TeselasTest extends TestCase
{
    public function test_el_mapa_se_sirve_entero_y_avisa_que_acepta_rangos(): void
    {
        $respuesta = $this->get('/mapa/parana.pmtiles');

        $respuesta->assertOk();
        $this->assertSame('bytes', $respuesta->headers->get('Accept-Ranges'));
        $this->assertGreaterThan(1_000_000, (int) filesize(resource_path('mapa/parana.pmtiles')));
    }

    public function test_un_pedido_de_rango_devuelve_solo_esos_bytes(): void
    {
        $respuesta = $this->get('/mapa/parana.pmtiles', ['Range' => 'bytes=0-6']);

        $respuesta->assertStatus(206);
        $this->assertSame('bytes 0-6/'.filesize(resource_path('mapa/parana.pmtiles')), $respuesta->headers->get('Content-Range'));
        // Un archivo PMTiles empieza con la palabra "PMTiles".
        $this->assertSame('PMTiles', $respuesta->streamedContent());
    }

    public function test_el_mapa_no_manda_cookies_para_poder_cachearse(): void
    {
        $respuesta = $this->get('/mapa/parana.pmtiles', ['Range' => 'bytes=0-6']);

        $this->assertEmpty($respuesta->headers->getCookies());
    }
}
