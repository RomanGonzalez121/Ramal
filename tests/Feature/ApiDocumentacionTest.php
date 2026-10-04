<?php

namespace Tests\Feature;

use App\Api\Documentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_rutas_de_la_api_v1_estan_documentadas_y_viceversa(): void
    {
        $enRutas = [];
        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with($ruta->uri(), 'api/v1/')) {
                continue;
            }
            foreach ($ruta->methods() as $metodo) {
                if ($metodo === 'HEAD') {
                    continue;
                }
                $enRutas[] = $metodo.' /'.preg_replace('/\{[^}]+\}/', '{}', $ruta->uri());
            }
        }
        sort($enRutas);

        $documentadas = array_map(fn ($f) => preg_replace('/^(\w+) \/api/', '$1 /api', $f), (new Documentacion)->firmas());

        $this->assertSame($enRutas, $documentadas, 'Hay rutas de /api/v1 sin documentar, o documentadas que no existen.');
    }

    public function test_las_rutas_que_piden_token_son_las_que_el_documento_dice(): void
    {
        $conToken = [];
        foreach (Route::getRoutes() as $ruta) {
            if (str_starts_with($ruta->uri(), 'api/v1/') && in_array('auth:sanctum', $ruta->gatherMiddleware(), true)) {
                $conToken[] = '/'.preg_replace('/\{[^}]+\}/', '{}', $ruta->uri());
            }
        }

        $documentadas = [];
        $doc = new Documentacion;
        foreach ($doc->operaciones() as $op) {
            if ($op['requiere_token']) {
                $documentadas[] = preg_replace('/\{[^}]+\}/', '{}', $doc->servidor().$op['ruta']);
            }
        }

        sort($conToken);
        sort($documentadas);
        $this->assertSame($conToken, $documentadas);
    }

    public function test_el_documento_se_descarga_como_yaml_y_como_json(): void
    {
        $yaml = $this->get('/api/openapi.yaml')->assertOk();
        $this->assertStringContainsString('openapi: 3.1.0', $yaml->getContent());

        $this->getJson('/api/openapi.json')->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'API pública de Ramal')
            ->assertJsonStructure(['paths' => ['/lineas' => ['get']]]);
    }

    public function test_la_pagina_de_documentacion_lista_cada_endpoint(): void
    {
        $respuesta = $this->get('/api')->assertOk();

        foreach ((new Documentacion)->operaciones() as $op) {
            $respuesta->assertSee($op['resumen']);
            $respuesta->assertSee('id="'.$op['id'].'"', false);
        }
    }

    public function test_la_pagina_de_tokens_pide_ingresar_y_es_solo_de_operadores(): void
    {
        $this->get('/operador/api')->assertRedirect('/operador/ingresar');

        $this->actingAs(User::factory()->create(['rol' => 'chofer']))->get('/operador/api')->assertForbidden();
        $this->actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]))->get('/operador/api')->assertOk()->assertSee('Acceso a la API');
    }

    public function test_un_operador_crea_un_token_lo_ve_una_sola_vez_y_funciona(): void
    {
        $operador = User::factory()->create(['rol' => User::ROL_OPERADOR]);

        $this->actingAs($operador)->post('/operador/api/tokens', ['nombre' => 'Mi app'])->assertRedirect('/operador/api');
        $valor = session('token_nuevo.valor');
        $this->assertNotNull($valor);

        $pagina = $this->actingAs($operador)->get('/operador/api')->assertOk();
        $pagina->assertSee('Mi app');
        $pagina->assertSee($valor);

        // La segunda vez que se mira la página ya no se muestra el valor completo, solo el nombre.
        $this->actingAs($operador)->get('/operador/api')->assertOk()->assertDontSee($valor)->assertSee('Mi app');

        $this->assertSame(1, $operador->tokens()->count());
        $this->assertSame(['leer'], $operador->tokens()->first()->abilities);
        $this->assertNotSame($valor, $operador->tokens()->first()->token, 'Se guarda el hash, no el valor');
    }

    public function test_el_nombre_del_token_es_obligatorio_y_hay_un_maximo_por_operador(): void
    {
        $operador = User::factory()->create(['rol' => User::ROL_OPERADOR]);

        $this->actingAs($operador)->post('/operador/api/tokens', ['nombre' => ''])->assertSessionHasErrors('nombre');

        config(['ramal.api.tokens_por_operador' => 2]);
        $this->actingAs($operador)->post('/operador/api/tokens', ['nombre' => 'Uno']);
        $this->actingAs($operador)->post('/operador/api/tokens', ['nombre' => 'Dos']);
        $this->actingAs($operador)->post('/operador/api/tokens', ['nombre' => 'Tres'])->assertSessionHasErrors('nombre');

        $this->assertSame(2, $operador->tokens()->count());
    }

    public function test_revocar_un_token_lo_borra_y_no_se_puede_revocar_el_de_otro_operador(): void
    {
        $a = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $b = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $tokenA = $a->createToken('de a', ['leer'])->accessToken;
        $tokenB = $b->createToken('de b', ['leer'])->accessToken;

        $this->actingAs($a)->delete("/operador/api/tokens/{$tokenB->id}")->assertNotFound();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenB->id]);

        $this->actingAs($a)->delete("/operador/api/tokens/{$tokenA->id}")->assertRedirect('/operador/api');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->id]);
    }

    public function test_un_nombre_de_token_con_comillas_no_rompe_la_pagina(): void
    {
        $operador = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $operador->createToken("x');alert(1);//<script>", ['leer']);

        $pagina = $this->actingAs($operador)->get('/operador/api')->assertOk();

        $pagina->assertDontSee('<script>alert', false);
        $pagina->assertDontSee("confirm('¿Revocar «x');alert(1)", false);
    }
}
