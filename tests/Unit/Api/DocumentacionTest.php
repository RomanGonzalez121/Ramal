<?php

namespace Tests\Unit\Api;

use App\Api\Documentacion;
use PHPUnit\Framework\TestCase;

class DocumentacionTest extends TestCase
{
    private function documentacion(): Documentacion
    {
        return new Documentacion(dirname(__DIR__, 3).'/resources/api/openapi.yaml');
    }

    public function test_el_documento_es_openapi_3_1_con_las_nueve_operaciones_de_lectura(): void
    {
        $doc = $this->documentacion();

        $this->assertSame('3.1.0', $doc->documento()['openapi']);
        $this->assertCount(9, $doc->operaciones());
    }

    public function test_todas_las_referencias_apuntan_a_algo_que_existe(): void
    {
        $doc = $this->documentacion();

        // `operaciones()` resuelve cada $ref y falla con una excepción si alguna apunta a la nada.
        foreach ($doc->operaciones() as $op) {
            foreach ($op['respuestas'] as $respuesta) {
                $this->assertNotSame('', $respuesta['descripcion'], "{$op['id']} {$respuesta['codigo']} sin descripción");
            }
        }

        $this->assertTrue(true);
    }

    public function test_cada_operacion_tiene_id_unico_resumen_y_una_respuesta_200(): void
    {
        $ids = [];
        foreach ($this->documentacion()->operaciones() as $op) {
            $this->assertNotContains($op['id'], $ids, 'operationId repetido');
            $ids[] = $op['id'];
            $this->assertNotSame('', $op['resumen'], "{$op['id']} sin resumen");
            $this->assertContains('200', array_column($op['respuestas'], 'codigo'), "{$op['id']} sin respuesta 200");
        }
    }

    public function test_el_ejemplo_de_una_respuesta_sigue_la_forma_del_esquema(): void
    {
        $op = collect($this->documentacion()->operaciones())->firstWhere('id', 'listarColectivos');
        $ok = collect($op['respuestas'])->firstWhere('codigo', '200');

        $this->assertArrayHasKey('data', $ok['ejemplo']);
        $this->assertArrayHasKey('meta', $ok['ejemplo']);
        $this->assertSame('circulando', $ok['ejemplo']['data'][0]['estado']);
    }

    public function test_las_operaciones_con_token_lo_declaran_y_el_estado_no(): void
    {
        $porId = collect($this->documentacion()->operaciones())->keyBy('id');

        $this->assertFalse($porId['estado']['requiere_token']);
        $this->assertTrue($porId['listarLineas']['requiere_token']);
    }

    public function test_los_tipos_se_explican_en_español(): void
    {
        $doc = $this->documentacion();

        $this->assertSame('entero', $doc->tipo(['type' => 'integer']));
        $this->assertSame('texto o nulo', $doc->tipo(['type' => ['string', 'null']]));
        $this->assertSame('lista de entero', $doc->tipo(['type' => 'array', 'items' => ['type' => 'integer']]));
    }
}
