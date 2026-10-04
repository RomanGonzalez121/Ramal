<?php

namespace Tests\Unit\Historial;

use App\Historial\Instantanea;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InstantaneaTest extends TestCase
{
    private function colectivo(array $cambios = []): array
    {
        return $cambios + ['id' => 7, 'ramal' => 3, 'lon' => -60.516753, 'lat' => -31.736902, 'rumbo' => -80, 'estado' => 'circulando'];
    }

    public function test_lo_que_se_empaqueta_se_recupera_igual(): void
    {
        $original = [$this->colectivo(), $this->colectivo(['id' => 12, 'ramal' => 9, 'lon' => -60.499999, 'lat' => -31.700001, 'rumbo' => 135, 'estado' => 'demorado'])];

        $recuperado = Instantanea::desempaquetar(Instantanea::empaquetar($original));

        $this->assertSame($original, $recuperado);
    }

    public function test_cada_colectivo_ocupa_quince_bytes(): void
    {
        $this->assertSame(15, strlen(Instantanea::empaquetar([$this->colectivo()])));
        $this->assertSame(15 * 40, strlen(Instantanea::empaquetar(array_fill(0, 40, $this->colectivo()))));
    }

    public function test_un_dia_entero_de_fotos_pesa_pocos_megabytes(): void
    {
        $bytesPorDia = strlen(Instantanea::empaquetar(array_fill(0, 40, $this->colectivo()))) * (86400 / 10);

        $this->assertLessThan(6 * 1024 * 1024, $bytesPorDia);
    }

    public function test_las_coordenadas_negativas_vuelven_con_su_signo(): void
    {
        $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['lon' => -60.0, 'lat' => -31.0])]))[0];

        $this->assertSame(-60.0, $c['lon']);
        $this->assertSame(-31.0, $c['lat']);
    }

    public function test_una_coordenada_positiva_tambien_se_conserva(): void
    {
        $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['lon' => 2.352222, 'lat' => 48.856614])]))[0];

        $this->assertSame(2.352222, $c['lon']);
        $this->assertSame(48.856614, $c['lat']);
    }

    public function test_la_precision_es_de_una_millonesima_de_grado(): void
    {
        $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['lon' => -60.5167534999, 'lat' => -31.7369024999])]))[0];

        $this->assertEqualsWithDelta(-60.5167534999, $c['lon'], 0.0000006);
        $this->assertEqualsWithDelta(-31.7369024999, $c['lat'], 0.0000006);
    }

    public function test_el_rumbo_conserva_sus_extremos_y_el_cero(): void
    {
        foreach ([-180, -179, -1, 0, 1, 90, 179, 180] as $rumbo) {
            $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['rumbo' => $rumbo])]))[0];
            $this->assertSame($rumbo, $c['rumbo'], "Rumbo {$rumbo}");
        }
    }

    public function test_todos_los_estados_se_guardan_y_vuelven(): void
    {
        foreach (Instantanea::ESTADOS as $estado) {
            $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['estado' => $estado])]))[0];
            $this->assertSame($estado, $c['estado']);
        }
    }

    public function test_los_estados_conservan_su_orden_porque_cambiarlo_rompe_el_historial_guardado(): void
    {
        $this->assertSame(
            ['circulando', 'en_parada', 'en_terminal', 'demorado', 'averiado', 'fuera_de_recorrido', 'fuera_de_servicio'],
            Instantanea::ESTADOS,
        );
    }

    public function test_los_identificadores_grandes_entran_en_dos_bytes(): void
    {
        $c = Instantanea::desempaquetar(Instantanea::empaquetar([$this->colectivo(['id' => 65535, 'ramal' => 65535])]))[0];

        $this->assertSame(65535, $c['id']);
        $this->assertSame(65535, $c['ramal']);
    }

    public function test_una_foto_vacia_es_un_bloque_vacio(): void
    {
        $this->assertSame('', Instantanea::empaquetar([]));
        $this->assertSame([], Instantanea::desempaquetar(''));
    }

    public function test_un_estado_desconocido_se_rechaza_al_guardar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Instantanea::empaquetar([$this->colectivo(['estado' => 'volando'])]);
    }

    public function test_un_bloque_cortado_se_rechaza_al_leer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Instantanea::desempaquetar(substr(Instantanea::empaquetar([$this->colectivo()]), 0, 14));
    }

    public function test_un_estado_que_no_existe_en_el_bloque_se_rechaza_al_leer(): void
    {
        $bloque = Instantanea::empaquetar([$this->colectivo()]);
        $bloque[14] = chr(200);

        $this->expectException(InvalidArgumentException::class);

        Instantanea::desempaquetar($bloque);
    }
}
