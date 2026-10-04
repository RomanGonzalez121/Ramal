<?php

namespace Tests\Unit;

use App\Support\Contraste;
use PHPUnit\Framework\TestCase;

class IdentidadTest extends TestCase
{
    public function test_negro_sobre_blanco_da_21_a_1(): void
    {
        $this->assertEqualsWithDelta(21.0, Contraste::ratio('#000000', '#FFFFFF'), 0.01);
    }

    public function test_un_color_contra_si_mismo_da_1_a_1(): void
    {
        $this->assertEqualsWithDelta(1.0, Contraste::ratio('#F5C400', '#F5C400'), 0.001);
    }

    public function test_el_orden_de_los_colores_no_cambia_el_resultado(): void
    {
        $this->assertSame(
            Contraste::ratio('#F5C400', '#1E2328'),
            Contraste::ratio('#1E2328', '#F5C400'),
        );
    }

    public function test_un_color_invalido_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Contraste::ratio('rojo', '#FFFFFF');
    }

    public function test_senal_sobre_asfalto_da_el_valor_de_la_memoria(): void
    {
        $this->assertSame('9,6:1', Contraste::formato('#F5C400', '#1E2328'));
    }

    public function test_senal_sobre_papel_no_sirve_como_texto(): void
    {
        $this->assertLessThan(2.0, Contraste::ratio('#F5C400', '#F7F7F4'));
    }
}
