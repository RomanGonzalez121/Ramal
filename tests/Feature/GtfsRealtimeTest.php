<?php

namespace Tests\Feature;

use App\Models\Posicion;
use App\Models\User;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GtfsRealtimeTest extends TestCase
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

    // ---------- Un lector de protobuf, solo para los tests: comprueba que lo que se escribe se puede volver a leer ----------

    /** @return array<int, array<int, mixed>> campo => lista de valores (enteros, bytes crudos o decimales sin interpretar) */
    private function leer(string $bytes): array
    {
        $campos = [];
        $i = 0;
        $n = strlen($bytes);

        while ($i < $n) {
            $etiqueta = $this->varint($bytes, $i);
            $campo = $etiqueta >> 3;
            $tipo = $etiqueta & 7;

            $campos[$campo][] = match ($tipo) {
                0 => $this->varint($bytes, $i),
                1 => $this->tomar($bytes, $i, 8),
                2 => $this->tomar($bytes, $i, $this->varint($bytes, $i)),
                5 => $this->tomar($bytes, $i, 4),
                default => $this->fail("Tipo de dato {$tipo} que el feed no debería usar"),
            };
        }

        return $campos;
    }

    private function varint(string $bytes, int &$i): int
    {
        $valor = 0;
        $desplazamiento = 0;
        do {
            $byte = ord($bytes[$i++]);
            $valor |= ($byte & 0x7F) << $desplazamiento;
            $desplazamiento += 7;
        } while ($byte & 0x80);

        return $valor;
    }

    private function tomar(string $bytes, int &$i, int $largo): string
    {
        $trozo = substr($bytes, $i, $largo);
        $i += $largo;

        return $trozo;
    }

    private function decimal32(string $bytes): float
    {
        return unpack('g', $bytes)[1];
    }

    private function pedir()
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);

        return $this->get('/api/v1/gtfs-rt/posiciones');
    }

    // ---------- Tests ----------

    public function test_sin_token_el_feed_pide_ingresar(): void
    {
        $this->getJson('/api/v1/gtfs-rt/posiciones')->assertUnauthorized();
    }

    public function test_responde_en_protobuf_por_defecto(): void
    {
        $r = $this->pedir()->assertOk();

        $this->assertSame('application/x-protobuf', $r->headers->get('Content-Type'));
        $this->assertNotSame('', $r->getContent());
    }

    public function test_el_feed_se_puede_volver_a_leer_y_trae_la_cabecera_de_la_especificacion(): void
    {
        $feed = $this->leer($this->pedir()->getContent());

        $cabecera = $this->leer($feed[1][0]);
        $this->assertSame('2.0', $cabecera[1][0]);
        $this->assertSame(0, $cabecera[2][0], 'FULL_DATASET');
        $this->assertEqualsWithDelta(now()->timestamp, $cabecera[3][0], 5);
    }

    public function test_hay_una_entidad_por_cada_colectivo_en_servicio_con_su_posicion(): void
    {
        $feed = $this->leer($this->pedir()->getContent());

        $this->assertCount(40, $feed[2]);

        $entidad = $this->leer($feed[2][0]);
        $this->assertNotEmpty($entidad[1][0], 'id de la entidad');

        $vehiculo = $this->leer($entidad[4][0]);
        $viaje = $this->leer($vehiculo[1][0]);
        $this->assertContains((int) $viaje[5][0], [1, 2, 3, 4, 5], 'route_id es el número de la línea');
        $this->assertContains($viaje[6][0] ?? 0, [0, 1], 'direction_id');

        $posicion = $this->leer($vehiculo[2][0]);
        $this->assertEqualsWithDelta(-31.73, $this->decimal32($posicion[1][0]), 0.1, 'latitud en Paraná');
        $this->assertEqualsWithDelta(-60.52, $this->decimal32($posicion[2][0]), 0.1, 'longitud en Paraná');

        $this->assertContains($vehiculo[4][0], [1, 2], 'current_status: STOPPED_AT o IN_TRANSIT_TO');
        $this->assertEqualsWithDelta(now()->timestamp, $vehiculo[5][0], 10);

        $descriptor = $this->leer($vehiculo[8][0]);
        $this->assertSame($entidad[1][0], $descriptor[1][0], 'el id del vehículo es el de la entidad');
        $this->assertMatchesRegularExpression('/^[1-5]\d{2}$/', $descriptor[2][0], 'la etiqueta es el número de interno');
    }

    public function test_el_protobuf_y_el_json_cuentan_lo_mismo(): void
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);

        $json = $this->getJson('/api/v1/gtfs-rt/posiciones?formato=json')->assertOk();
        $json->assertJsonPath('header.gtfs_realtime_version', '2.0')
            ->assertJsonPath('header.incrementality', 'FULL_DATASET')
            ->assertJsonCount(40, 'entity');

        $feed = $this->leer($this->get('/api/v1/gtfs-rt/posiciones')->getContent());
        $primero = $this->leer($this->leer($feed[2][0])[4][0]);
        $enJson = $json->json('entity.0.vehicle');

        $posicion = $this->leer($primero[2][0]);
        $this->assertEqualsWithDelta($enJson['position']['latitude'], $this->decimal32($posicion[1][0]), 0.0001);
        $this->assertEqualsWithDelta($enJson['position']['longitude'], $this->decimal32($posicion[2][0]), 0.0001);
        $this->assertSame($enJson['vehicle']['label'], $this->leer($primero[8][0])[2][0]);
    }

    public function test_un_colectivo_detenido_en_una_parada_informa_stopped_at_y_esa_parada(): void
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);
        $posicion = Posicion::with('ramal.paradas')->first();
        $parada = $posicion->ramal->paradas->first();
        $posicion->update(['estado' => 'en_parada', 'ultima_parada' => $parada->pivot->orden]);

        $entidad = collect($this->getJson('/api/v1/gtfs-rt/posiciones?formato=json')->json('entity'))
            ->firstWhere('id', (string) $posicion->colectivo_id);

        $this->assertSame(1, $entidad['vehicle']['current_status']);
        $this->assertSame((string) $parada->id, $entidad['vehicle']['stop_id']);
        $this->assertSame($parada->pivot->orden, $entidad['vehicle']['current_stop_sequence']);
    }

    public function test_un_colectivo_en_camino_informa_in_transit_to_la_proxima_parada(): void
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);
        $posicion = Posicion::with('ramal.paradas')->first();
        $paradas = $posicion->ramal->paradas->values();
        $posicion->update(['estado' => 'circulando', 'ultima_parada' => $paradas[0]->pivot->orden]);

        $entidad = collect($this->getJson('/api/v1/gtfs-rt/posiciones?formato=json')->json('entity'))
            ->firstWhere('id', (string) $posicion->colectivo_id);

        $this->assertSame(2, $entidad['vehicle']['current_status']);
        $this->assertSame((string) $paradas[1]->id, $entidad['vehicle']['stop_id']);
    }

    public function test_los_fuera_de_servicio_no_aparecen(): void
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);
        Posicion::query()->limit(3)->update(['estado' => 'fuera_de_servicio']);

        $this->getJson('/api/v1/gtfs-rt/posiciones?formato=json')->assertJsonCount(37, 'entity');
    }

    public function test_un_formato_desconocido_da_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['rol' => User::ROL_OPERADOR]), ['leer']);

        $this->getJson('/api/v1/gtfs-rt/posiciones?formato=xml')->assertUnprocessable()->assertJsonValidationErrors('formato');
    }
}
