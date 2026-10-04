<?php

namespace Tests\Unit\Simulacion;

use App\Simulacion\Celdas;
use PHPUnit\Framework\TestCase;

class CeldasTest extends TestCase
{
    private function celdas(): Celdas
    {
        return new Celdas([-60.62, -31.82, -60.42, -31.66], 4, 4);
    }

    public function test_cada_punto_cae_en_una_celda(): void
    {
        $c = $this->celdas();

        $this->assertSame('0.0', $c->de(-60.61, -31.67));  // noroeste
        $this->assertSame('3.3', $c->de(-60.43, -31.81));  // sudeste
        $this->assertSame('2.1', $c->de(-60.51, -31.73));
    }

    public function test_un_punto_fuera_de_la_caja_se_pega_al_borde(): void
    {
        $this->assertSame('0.0', $this->celdas()->de(-61.0, -30.0));
        $this->assertSame('3.3', $this->celdas()->de(-59.0, -33.0));
    }

    public function test_una_vista_chica_toca_pocas_celdas_y_una_grande_las_toca_todas(): void
    {
        $c = $this->celdas();

        $this->assertSame(['2.1'], $c->queCruzan([-60.515, -31.738, -60.505, -31.72]));
        $this->assertCount(16, $c->queCruzan([-60.62, -31.82, -60.42, -31.66]));
        $this->assertCount(16, $c->todas());
    }

    public function test_una_vista_que_cruza_el_borde_de_dos_celdas_toca_las_dos(): void
    {
        $celdas = $this->celdas()->queCruzan([-60.53, -31.738, -60.48, -31.72]);

        $this->assertContains('2.1', $celdas);
        $this->assertContains('1.1', $celdas);
        $this->assertCount(2, array_filter($celdas, fn ($c) => str_ends_with($c, '.1')));
    }
}
