<?php

namespace Tests\Feature;

use App\Models\Parada;
use App\Models\Posicion;
use App\Models\User;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);
        $servicio = new ServicioSimulacion;
        $servicio->preparar();
        $servicio->tick(2.0);
    }

    private function conToken(array $permisos = ['leer']): User
    {
        $usuario = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        Sanctum::actingAs($usuario, $permisos);

        return $usuario;
    }

    public function test_sin_token_la_api_responde_401_en_español(): void
    {
        foreach (['/api/v1/lineas', '/api/v1/lineas/1', '/api/v1/paradas', '/api/v1/colectivos', '/api/v1/paradas/1/llegadas'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized()->assertJsonStructure(['mensaje']);
        }
    }

    public function test_un_token_sin_permiso_de_lectura_recibe_403(): void
    {
        $this->conToken(['otra-cosa']);

        $this->getJson('/api/v1/lineas')->assertForbidden()->assertJsonPath('mensaje', 'Este token no tiene permiso para esto.');
    }

    public function test_el_estado_no_pide_token_y_dice_si_la_simulacion_anda(): void
    {
        $this->getJson('/api/v1/estado')
            ->assertOk()
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.colectivos_en_servicio', 40)
            ->assertJsonPath('data.simulacion', 'activa');
    }

    public function test_el_estado_avisa_cuando_la_simulacion_esta_detenida(): void
    {
        $this->travel(5)->minutes();

        $this->getJson('/api/v1/estado')->assertOk()->assertJsonPath('data.simulacion', 'detenida');
    }

    public function test_lista_las_cinco_lineas_con_sus_ramales(): void
    {
        $this->conToken();

        $r = $this->getJson('/api/v1/lineas')->assertOk();

        $r->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.numero', 1)
            ->assertJsonCount(2, 'data.0.ramales')
            ->assertJsonStructure(['data' => [['numero', 'nombre', 'destino', 'ramales' => [['id', 'sentido', 'destino', 'cantidad_paradas']]]]]);
    }

    public function test_el_detalle_de_una_linea_trae_paradas_en_orden_y_el_trazado_como_geojson(): void
    {
        $this->conToken();

        $r = $this->getJson('/api/v1/lineas/3')->assertOk();

        $r->assertJsonPath('data.numero', 3)
            ->assertJsonPath('data.ramales.0.recorrido.type', 'LineString');

        $this->assertGreaterThan(30, count($r->json('data.ramales.0.recorrido.coordinates')));

        $ordenes = array_column($r->json('data.ramales.0.paradas'), 'orden');
        $ordenadas = $ordenes;
        sort($ordenadas);
        $this->assertSame($ordenadas, $ordenes);
        $this->assertNotEmpty($ordenes);
    }

    public function test_una_linea_que_no_existe_es_404_en_español(): void
    {
        $this->conToken();

        $this->getJson('/api/v1/lineas/99')->assertNotFound()->assertJsonPath('mensaje', 'No encontramos lo que pediste.');
    }

    public function test_lista_las_paradas_y_filtra_por_linea(): void
    {
        $this->conToken();

        $todas = $this->getJson('/api/v1/paradas')->assertOk()->assertJsonCount(11, 'data');
        $this->assertSame(['id', 'nombre', 'latitud', 'longitud', 'lineas'], array_keys($todas->json('data.0')));

        $deLaTres = $this->getJson('/api/v1/paradas?linea=3')->assertOk();
        $this->assertLessThan(11, count($deLaTres->json('data')));
        foreach ($deLaTres->json('data') as $parada) {
            $this->assertContains(3, $parada['lineas']);
        }
    }

    public function test_busca_las_paradas_cercanas_ordenadas_por_distancia(): void
    {
        $this->conToken();
        $parada = Parada::query()->first();

        $r = $this->getJson("/api/v1/paradas?cerca={$parada->latitud},{$parada->longitud}&radio_m=1500")->assertOk();

        $this->assertSame($parada->id, $r->json('data.0.id'));
        $this->assertSame(0, $r->json('data.0.distancia_m'));

        $distancias = array_column($r->json('data'), 'distancia_m');
        $ordenadas = $distancias;
        sort($ordenadas);
        $this->assertSame($ordenadas, $distancias);
        $this->assertLessThanOrEqual(1500, max($distancias));
    }

    public function test_los_parametros_invalidos_dan_422_con_mensaje_en_español(): void
    {
        $this->conToken();

        $this->getJson('/api/v1/paradas?cerca=Plaza')->assertUnprocessable()->assertJsonValidationErrors('cerca');
        $this->getJson('/api/v1/paradas?cerca=-31.7,-60.5&radio_m=9')->assertUnprocessable()->assertJsonValidationErrors('radio_m');
        $this->getJson('/api/v1/colectivos?estado=volando')->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->getJson('/api/v1/colectivos?linea=tres')->assertUnprocessable()->assertJsonValidationErrors('linea');
    }

    public function test_el_detalle_de_una_parada_y_sus_llegadas(): void
    {
        $this->conToken();
        $parada = Parada::query()->first();

        $this->getJson("/api/v1/paradas/{$parada->id}")->assertOk()->assertJsonPath('data.nombre', $parada->nombre);

        $r = $this->getJson("/api/v1/paradas/{$parada->id}/llegadas")->assertOk();
        $r->assertJsonPath('data.parada.id', $parada->id)
            ->assertJsonStructure(['data' => ['parada', 'calculado_en', 'servicio', 'proximo_servicio', 'llegadas']]);

        $segundos = array_column($r->json('data.llegadas'), 'segundos');
        $ordenadas = $segundos;
        sort($ordenadas);
        $this->assertSame($ordenadas, $segundos);
    }

    public function test_lista_los_cuarenta_colectivos_y_filtra_por_linea_y_estado(): void
    {
        $this->conToken();

        $r = $this->getJson('/api/v1/colectivos')->assertOk();
        $r->assertJsonCount(40, 'data')->assertJsonPath('meta.cantidad', 40);

        $primero = $r->json('data.0');
        foreach (['id', 'interno', 'linea', 'ramal_id', 'destino', 'estado', 'latitud', 'longitud', 'rumbo', 'velocidad_kmh', 'actualizado_en'] as $clave) {
            $this->assertArrayHasKey($clave, $primero);
        }

        $deLaDos = $this->getJson('/api/v1/colectivos?linea=2')->assertOk();
        $this->assertNotEmpty($deLaDos->json('data'));
        $this->assertCount(8, $deLaDos->json('data'));
        foreach ($deLaDos->json('data') as $c) {
            $this->assertSame(2, $c['linea']);
        }

        Posicion::query()->where('colectivo_id', $primero['id'])->update(['estado' => 'averiado']);
        $averiados = $this->getJson('/api/v1/colectivos?estado=averiado')->assertOk();
        $this->assertSame([$primero['id']], array_column($averiados->json('data'), 'id'));
    }

    public function test_los_colectivos_fuera_de_servicio_no_se_listan_ni_se_pueden_pedir(): void
    {
        $this->conToken();
        $id = $this->getJson('/api/v1/colectivos')->json('data.0.id');

        $this->getJson("/api/v1/colectivos/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        Posicion::query()->where('colectivo_id', $id)->update(['estado' => 'fuera_de_servicio']);

        $this->getJson('/api/v1/colectivos')->assertJsonCount(39, 'data');
        $this->getJson("/api/v1/colectivos/{$id}")->assertNotFound()->assertJsonPath('mensaje', 'Ese colectivo está fuera de servicio.');
    }

    public function test_cada_token_tiene_su_cupo_por_minuto(): void
    {
        config(['ramal.api.limite_por_minuto' => 3]);
        $a = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $b = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $tokenA = $a->createToken('a', ['leer'])->plainTextToken;
        $tokenB = $b->createToken('b', ['leer'])->plainTextToken;

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($tokenA)->getJson('/api/v1/lineas')->assertOk();
        }

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA)->getJson('/api/v1/lineas')
            ->assertStatus(429)
            ->assertJsonStructure(['mensaje', 'reintentar_en_s']);

        // El cupo de otro token no se toca.
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/v1/lineas')->assertOk();
    }

    public function test_un_token_real_funciona_en_la_cabecera_y_revocado_deja_de_funcionar(): void
    {
        $usuario = User::factory()->create(['rol' => User::ROL_OPERADOR]);
        $nuevo = $usuario->createToken('prueba', ['leer']);

        $this->withToken($nuevo->plainTextToken)->getJson('/api/v1/lineas')->assertOk();

        $nuevo->accessToken->delete();
        $this->app['auth']->forgetGuards();

        $this->withToken($nuevo->plainTextToken)->getJson('/api/v1/lineas')->assertUnauthorized();
    }
}
