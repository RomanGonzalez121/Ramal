<?php

namespace Tests\Feature;

use App\Models\Colectivo;
use App\Models\Desvio;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\User;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use App\Support\Geo;
use Database\Seeders\CiudadSeeder;
use Database\Seeders\OperadorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M7: el ciclo del incidente (se genera, el operador lo ve, lo atiende, se resuelve) y el desvío de verdad,
 * contra la base de datos con las líneas de Paraná.
 */
class IncidentesTest extends TestCase
{
    use RefreshDatabase;

    private ServicioSimulacion $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([CiudadSeeder::class, OperadorSeeder::class]);
        config(['ramal.siempre_en_servicio' => true]);

        $this->servicio = new ServicioSimulacion;
        $this->servicio->preparar();
    }

    private function operador(): User
    {
        return User::where('email', OperadorSeeder::CORREO_DEMO)->firstOrFail();
    }

    /** Le arma un incidente a un colectivo, con la posición y el registro consistentes entre sí. */
    private function incidente(string $interno, string $tipo, float $restante = 600.0, array $posicion = []): Incidente
    {
        $colectivo = Colectivo::where('interno', $interno)->firstOrFail();
        $estado = match ($tipo) {
            'falla' => EstadoColectivo::AVERIADO,
            'demora' => EstadoColectivo::DEMORADO,
            default => EstadoColectivo::CIRCULANDO,
        };

        Posicion::where('colectivo_id', $colectivo->id)->update($posicion + [
            'estado' => $estado,
            'incidente_tipo' => $tipo,
            'incidente_restante_s' => $restante,
        ]);

        return Incidente::create([
            'colectivo_id' => $colectivo->id,
            'tipo' => $tipo,
            'duracion_prevista_s' => (int) $restante,
            'inicio_en' => now()->subMinutes(2),
        ]);
    }

    // ---------- acciones del operador ----------

    public function test_quien_no_ingreso_no_puede_atender_ni_resolver(): void
    {
        $incidente = $this->incidente('101', 'demora');

        $this->postJson("/operador/incidentes/{$incidente->id}/atender")->assertUnauthorized();
        $this->postJson("/operador/incidentes/{$incidente->id}/resolver")->assertUnauthorized();
        $this->assertSame('activo', $incidente->fresh()->estado);
    }

    public function test_una_cuenta_sin_rol_de_operador_tampoco(): void
    {
        $incidente = $this->incidente('101', 'demora');
        $visitante = User::factory()->create(['rol' => 'visitante']);

        $this->actingAs($visitante)->postJson("/operador/incidentes/{$incidente->id}/atender")->assertForbidden();
        $this->assertSame('activo', $incidente->fresh()->estado);
    }

    public function test_atender_pasa_el_incidente_a_atendido_y_deja_constancia_de_quien_fue(): void
    {
        $incidente = $this->incidente('101', 'demora');

        $this->actingAs($this->operador())
            ->postJson("/operador/incidentes/{$incidente->id}/atender")
            ->assertOk()
            ->assertJsonPath('estado', 'atendido');

        $incidente->refresh();
        $this->assertSame('atendido', $incidente->estado);
        $this->assertSame($this->operador()->id, $incidente->atendido_por);
        $this->assertNotNull($incidente->atendido_en);
    }

    public function test_no_se_puede_atender_dos_veces(): void
    {
        $incidente = $this->incidente('101', 'demora');
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/atender")->assertOk();

        $this->actingAs($this->operador())
            ->postJson("/operador/incidentes/{$incidente->id}/atender")
            ->assertStatus(409)
            ->assertJsonPath('mensaje', 'Ese incidente ya está atendido o resuelto.');
    }

    public function test_resolver_anota_el_pedido_y_atiende_en_el_mismo_paso(): void
    {
        $incidente = $this->incidente('102', 'falla');

        $this->actingAs($this->operador())
            ->postJson("/operador/incidentes/{$incidente->id}/resolver")
            ->assertStatus(202);

        $incidente->refresh();
        $this->assertSame('resolver', $incidente->accion_pedida);
        $this->assertSame('atendido', $incidente->estado);
        $this->assertSame($this->operador()->id, $incidente->atendido_por);
    }

    public function test_un_desvio_no_se_puede_dar_por_resuelto_a_mano(): void
    {
        $incidente = $this->incidente('103', 'desvio');

        $this->actingAs($this->operador())
            ->postJson("/operador/incidentes/{$incidente->id}/resolver")
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Un desvío se cierra solo cuando el colectivo vuelve al recorrido.');

        $this->assertNull($incidente->fresh()->accion_pedida);
    }

    public function test_un_incidente_resuelto_no_admite_mas_acciones(): void
    {
        $incidente = $this->incidente('104', 'demora');
        $incidente->update(['estado' => 'resuelto', 'fin_en' => now()]);

        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/resolver")->assertStatus(409);
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/atender")->assertStatus(409);
    }

    public function test_pedir_dos_veces_resolver_no_rompe_nada(): void
    {
        $incidente = $this->incidente('102', 'falla');
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/resolver")->assertStatus(202);

        $this->actingAs($this->operador())
            ->postJson("/operador/incidentes/{$incidente->id}/resolver")
            ->assertOk()
            ->assertJsonPath('mensaje', 'Ya se pidió resolverlo; se aplica en unos segundos.');
    }

    public function test_un_incidente_que_no_existe_da_404(): void
    {
        $this->actingAs($this->operador())->postJson('/operador/incidentes/99999/atender')->assertNotFound();
    }

    // ---------- el simulador aplica lo que pidió el operador ----------

    public function test_atender_una_falla_la_acorta_en_el_siguiente_tick(): void
    {
        $incidente = $this->incidente('201', 'falla', 500.0);
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/atender")->assertOk();

        $this->servicio->tick(2.0);

        $posicion = Posicion::where('colectivo_id', $incidente->colectivo_id)->first();
        $this->assertLessThanOrEqual(120.0, $posicion->incidente_restante_s);
        $this->assertSame('falla', $posicion->incidente_tipo, 'Atender no la resuelve, la acorta');
        $this->assertTrue($incidente->fresh()->ajuste_aplicado);
    }

    public function test_la_atencion_se_aplica_una_sola_vez(): void
    {
        $incidente = $this->incidente('201', 'falla', 500.0);
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/atender")->assertOk();
        $this->servicio->tick(2.0);

        $restanteTrasAtender = Posicion::where('colectivo_id', $incidente->colectivo_id)->value('incidente_restante_s');
        $this->servicio->tick(2.0);

        $this->assertEqualsWithDelta($restanteTrasAtender - 2.0, Posicion::where('colectivo_id', $incidente->colectivo_id)->value('incidente_restante_s'), 0.5);
    }

    public function test_resolver_termina_el_incidente_en_el_siguiente_tick_y_el_colectivo_vuelve_a_andar(): void
    {
        $incidente = $this->incidente('202', 'falla', 500.0);
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/resolver")->assertStatus(202);

        $this->servicio->tick(2.0);

        $incidente->refresh();
        $posicion = Posicion::where('colectivo_id', $incidente->colectivo_id)->first();

        $this->assertSame('resuelto', $incidente->estado);
        $this->assertSame('operador', $incidente->resuelto_por);
        $this->assertNotNull($incidente->fin_en);
        $this->assertNull($incidente->accion_pedida);
        $this->assertNull($posicion->incidente_tipo);
        $this->assertNotSame(EstadoColectivo::AVERIADO, $posicion->estado);
    }

    public function test_un_incidente_sin_pedidos_sigue_su_curso_y_se_resuelve_solo_al_terminar(): void
    {
        $incidente = $this->incidente('203', 'demora', 3.0);

        $this->servicio->tick(2.0);
        $this->assertSame('activo', $incidente->fresh()->estado);

        $this->servicio->tick(2.0);
        $incidente->refresh();

        $this->assertSame('resuelto', $incidente->estado);
        $this->assertSame('simulacion', $incidente->resuelto_por);
    }

    public function test_un_incidente_atendido_tambien_se_resuelve_solo_cuando_se_termina(): void
    {
        $incidente = $this->incidente('204', 'demora', 3.0);
        $this->actingAs($this->operador())->postJson("/operador/incidentes/{$incidente->id}/atender")->assertOk();

        $this->servicio->tick(2.0);
        $this->servicio->tick(2.0);

        $this->assertSame('resuelto', $incidente->fresh()->estado);
    }

    // ---------- desvíos con los caminos alternativos de Paraná ----------

    public function test_el_seeder_cargo_caminos_alternativos_para_casi_todos_los_tramos(): void
    {
        $this->assertGreaterThanOrEqual(30, Desvio::count());

        foreach (Desvio::with('ramal.paradas')->get() as $desvio) {
            $this->assertGreaterThan(10, count($desvio->puntos));
            $this->assertCount(count($desvio->puntos), $desvio->distancias);
            $distancias = $desvio->distancias;
            $this->assertEqualsWithDelta(end($distancias), $desvio->largo_m, 1.0);
        }
    }

    public function test_cada_camino_alternativo_sale_de_una_parada_y_vuelve_a_la_siguiente(): void
    {
        foreach (Desvio::with('ramal.paradas')->get() as $desvio) {
            $paradas = $desvio->ramal->paradas->keyBy(fn ($p) => $p->pivot->orden);
            $salida = $paradas[$desvio->desde_orden];
            $llegada = $paradas[$desvio->desde_orden + 1];

            $primero = $desvio->puntos[0];
            $ultimo = $desvio->puntos[array_key_last($desvio->puntos)];

            $this->assertLessThan(15, Geo::distanciaMetros($primero, [$salida->longitud, $salida->latitud]), "Desvío {$desvio->id}: no sale de su parada");
            $this->assertLessThan(15, Geo::distanciaMetros($ultimo, [$llegada->longitud, $llegada->latitud]), "Desvío {$desvio->id}: no llega a la parada siguiente");
        }
    }

    public function test_cada_camino_alternativo_es_bastante_mas_largo_que_el_tramo_normal(): void
    {
        foreach (Desvio::with('ramal.paradas')->get() as $desvio) {
            $paradas = $desvio->ramal->paradas->keyBy(fn ($p) => $p->pivot->orden);
            $normal = $paradas[$desvio->desde_orden + 1]->pivot->distancia_m - $paradas[$desvio->desde_orden]->pivot->distancia_m;

            $this->assertGreaterThanOrEqual(1.1, $desvio->largo_m / $normal, "Desvío {$desvio->id}");
            $this->assertLessThanOrEqual(2.6, $desvio->largo_m / $normal, "Desvío {$desvio->id}");
        }
    }

    /** Un colectivo a punto de salir de la parada donde empieza un desvío real, con ese desvío ya en marcha. */
    private function conDesvioReal(): array
    {
        $desvio = Desvio::with('ramal.paradas', 'ramal.linea')->orderBy('id')->firstOrFail();
        $paradas = $desvio->ramal->paradas->keyBy(fn ($p) => $p->pivot->orden);
        $colectivo = Colectivo::where('linea_id', $desvio->ramal->linea_id)->orderBy('interno')->firstOrFail();

        $salida = $paradas[$desvio->desde_orden]->pivot->distancia_m;

        Posicion::where('colectivo_id', $colectivo->id)->update([
            'ramal_id' => $desvio->ramal_id,
            'distancia_m' => $salida - 40,
            'ultima_parada' => max(0, $desvio->desde_orden - 1),
            'velocidad_ms' => 5,
            'estado' => EstadoColectivo::CIRCULANDO,
            'espera_s' => 0,
            'incidente_tipo' => 'desvio',
            'incidente_restante_s' => 400,
            'desvio_orden' => $desvio->desde_orden,
        ]);

        $incidente = Incidente::create([
            'colectivo_id' => $colectivo->id,
            'tipo' => 'desvio',
            'desvio_orden' => $desvio->desde_orden,
            'duracion_prevista_s' => 400,
            'inicio_en' => now(),
        ]);

        return [$desvio, $colectivo, $incidente];
    }

    public function test_un_colectivo_con_desvio_se_aparta_del_recorrido_y_vuelve(): void
    {
        [$desvio, $colectivo, $incidente] = $this->conDesvioReal();
        $recorrido = $desvio->ramal->recorrido;

        $maxLejos = 0.0;
        $fueraDeRecorrido = false;

        for ($i = 0; $i < 600; $i++) {
            $this->servicio->tick(10.0);
            $posicion = Posicion::where('colectivo_id', $colectivo->id)->first();

            if ($posicion->estado === EstadoColectivo::FUERA_DE_RECORRIDO) {
                $fueraDeRecorrido = true;
                $lejos = Geo::proyectar([$posicion->longitud, $posicion->latitud], $recorrido->puntos, $recorrido->distancias)['distancia_al_trazado_m'];
                $maxLejos = max($maxLejos, $lejos);
            }

            if ($incidente->fresh()->estado === 'resuelto') {
                break;
            }
        }

        $this->assertTrue($fueraDeRecorrido, 'Pasó al estado fuera de recorrido');
        $this->assertGreaterThan(100, $maxLejos, 'En algún momento estuvo a más de 100 m del recorrido normal');

        $incidente->refresh();
        $posicion = Posicion::where('colectivo_id', $colectivo->id)->first();
        $this->assertSame('resuelto', $incidente->estado);
        $this->assertSame('simulacion', $incidente->resuelto_por, 'Se cerró solo al volver al recorrido');
        $this->assertNull($posicion->desvio_orden);
        $this->assertNull($posicion->incidente_tipo);
        $this->assertNotSame(EstadoColectivo::FUERA_DE_RECORRIDO, $posicion->estado);
    }

    public function test_el_mapa_recibe_el_camino_alternativo_de_los_colectivos_desviados(): void
    {
        $this->getJson('/api/desvios')->assertOk()->assertJsonCount(0, 'desvios');

        [$desvio, $colectivo] = $this->conDesvioReal();

        $respuesta = $this->getJson('/api/desvios')->assertOk()->assertJsonCount(1, 'desvios');

        $this->assertSame($colectivo->interno, $respuesta->json('desvios.0.interno'));
        $this->assertSame($desvio->ramal->linea->numero, $respuesta->json('desvios.0.linea'));
        $this->assertCount(count($desvio->puntos), $respuesta->json('desvios.0.puntos'));
    }

    public function test_los_mensajes_en_vivo_traen_el_trazado_por_el_camino_alternativo(): void
    {
        [$desvio, $colectivo] = $this->conDesvioReal();
        $normal = $desvio->ramal->recorrido;

        // Se sigue al colectivo durante todo el desvío (las primeras cuadras coinciden con el recorrido normal).
        $trazados = [];
        for ($i = 0; $i < 600; $i++) {
            $emitido = collect($this->servicio->tick(10.0))->flatten(1)->firstWhere('id', $colectivo->id);
            if ($emitido && $emitido['estado'] === EstadoColectivo::FUERA_DE_RECORRIDO && count($emitido['ruta']) > 1) {
                $trazados[] = $emitido['ruta'];
            }
            if (Incidente::where('colectivo_id', $colectivo->id)->where('estado', 'resuelto')->exists()) {
                break;
            }
        }

        $this->assertNotEmpty($trazados);
        $lejos = collect($trazados)->flatten(1)->map(
            fn ($punto) => Geo::proyectar($punto, $normal->puntos, $normal->distancias)['distancia_al_trazado_m']
        )->max();
        $this->assertGreaterThan(50, $lejos, 'Los puntos del mensaje siguen el camino alternativo, no el recorrido');
    }
}
