<?php

namespace Tests\Feature;

use App\Events\PosicionesActualizadas;
use App\Historial\Instantanea;
use App\Models\Colectivo;
use App\Models\HistorialPosicion;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\Simulacion;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * M8: el historial para rebobinar. Se guarda una foto cada 10 s, se pide por ventanas cortas y se borra a las 48 h.
 */
class HistorialTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Argentina/Buenos_Aires';

    private ServicioSimulacion $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);

        $this->servicio = new ServicioSimulacion;
        $this->servicio->preparar();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function foto(string $momento, array $colectivos = []): HistorialPosicion
    {
        return HistorialPosicion::create([
            'momento' => Carbon::parse($momento, 'UTC'),
            'tick' => 1,
            'cantidad' => count($colectivos),
            'datos' => Instantanea::empaquetar($colectivos),
        ]);
    }

    private function unColectivo(int $id = 1): array
    {
        return ['id' => $id, 'ramal' => 1, 'lon' => -60.5167, 'lat' => -31.7369, 'rumbo' => -80, 'estado' => 'circulando'];
    }

    // ---------- se guarda ----------

    public function test_el_primer_tick_guarda_una_foto_con_todos_los_colectivos_en_servicio(): void
    {
        $this->servicio->tick(2.0);

        $this->assertSame(1, HistorialPosicion::count());
        $foto = HistorialPosicion::first();
        $this->assertSame(40, $foto->cantidad);
        $this->assertCount(40, $foto->colectivos());
        $this->assertSame(600, strlen($foto->datos));
    }

    public function test_la_foto_coincide_con_las_posiciones_de_ese_momento(): void
    {
        $this->servicio->tick(2.0);

        $foto = collect(HistorialPosicion::first()->colectivos())->keyBy('id');

        foreach (Posicion::all() as $p) {
            $this->assertEqualsWithDelta($p->longitud, $foto[$p->colectivo_id]['lon'], 0.0000006);
            $this->assertEqualsWithDelta($p->latitud, $foto[$p->colectivo_id]['lat'], 0.0000006);
            $this->assertSame($p->ramal_id, $foto[$p->colectivo_id]['ramal']);
            $this->assertSame($p->estado, $foto[$p->colectivo_id]['estado']);
        }
    }

    public function test_no_se_guarda_una_foto_por_cada_tick_sino_una_cada_diez_segundos(): void
    {
        $inicio = Carbon::parse('2026-10-05 10:00:00', self::ZONA);

        foreach (range(0, 9) as $i) {
            $this->servicio->tick(2.0, $inicio->copy()->addSeconds($i * 2));
        }

        // 10 ticks de 2 s son 20 s: fotos en 0, 10 y (por poco) no en 20.
        $this->assertGreaterThanOrEqual(2, HistorialPosicion::count());
        $this->assertLessThanOrEqual(3, HistorialPosicion::count());
    }

    public function test_si_los_ticks_son_de_diez_segundos_se_guarda_una_foto_por_tick(): void
    {
        $inicio = Carbon::parse('2026-10-05 10:00:00', self::ZONA);

        foreach (range(0, 5) as $i) {
            $this->servicio->tick(10.0, $inicio->copy()->addSeconds($i * 10));
        }

        $this->assertSame(6, HistorialPosicion::count());
    }

    public function test_el_momento_de_la_foto_es_el_de_la_simulacion_y_se_guarda_en_utc(): void
    {
        $this->servicio->tick(2.0, Carbon::parse('2026-10-05 10:00:00', self::ZONA));

        $this->assertSame('2026-10-05 13:00:00', HistorialPosicion::first()->momento->format('Y-m-d H:i:s'));
    }

    public function test_los_colectivos_fuera_de_servicio_no_van_en_la_foto(): void
    {
        config(['ramal.siempre_en_servicio' => false]);

        // De madrugada no hay servicio: la foto sale vacía.
        $this->servicio->tick(2.0, Carbon::parse('2026-10-05 03:00:00', self::ZONA));
        $noche = HistorialPosicion::first();
        $this->assertSame(0, $noche->cantidad);
        $this->assertSame('', $noche->datos);

        // A media mañana todos están en la calle.
        $this->servicio->tick(2.0, Carbon::parse('2026-10-05 10:00:00', self::ZONA));
        $dia = HistorialPosicion::orderByDesc('momento')->first();
        $this->assertSame(40, $dia->cantidad);
        $this->assertNotContains(EstadoColectivo::FUERA_DE_SERVICIO, array_column($dia->colectivos(), 'estado'));
    }

    public function test_un_servicio_nuevo_retoma_desde_la_ultima_foto_guardada_y_no_repite(): void
    {
        $ahora = Carbon::parse('2026-10-05 10:00:00', self::ZONA);
        $this->servicio->tick(2.0, $ahora);

        $otroServicio = new ServicioSimulacion;
        $otroServicio->tick(2.0, $ahora->copy()->addSeconds(2));

        $this->assertSame(1, HistorialPosicion::count(), 'Dos segundos después todavía no toca otra foto');
    }

    // ---------- la API ----------

    public function test_el_rango_dice_desde_y_hasta_cuando_hay_datos(): void
    {
        $this->foto('2026-10-05 12:00:00', [$this->unColectivo()]);
        $this->foto('2026-10-05 12:10:00', [$this->unColectivo()]);

        $this->getJson('/api/historial/rango')
            ->assertOk()
            ->assertJsonPath('desde', '2026-10-05T12:00:00+00:00')
            ->assertJsonPath('hasta', '2026-10-05T12:10:00+00:00')
            ->assertJsonPath('fotos', 2)
            ->assertJsonPath('paso_s', 10)
            ->assertJsonPath('retencion_horas', 48);
    }

    public function test_el_rango_sin_datos_viene_vacio_y_no_rompe(): void
    {
        $this->getJson('/api/historial/rango')
            ->assertOk()
            ->assertJsonPath('desde', null)
            ->assertJsonPath('hasta', null)
            ->assertJsonPath('fotos', 0);
    }

    public function test_pedir_una_ventana_devuelve_las_fotos_de_ese_tramo_en_orden(): void
    {
        $this->foto('2026-10-05 12:00:20', [$this->unColectivo(2)]);
        $this->foto('2026-10-05 12:00:00', [$this->unColectivo(1)]);
        $this->foto('2026-10-05 12:00:10', [$this->unColectivo(3)]);
        $this->foto('2026-10-05 12:30:00', [$this->unColectivo(9)]);   // fuera de la ventana

        $respuesta = $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:01:00Z')->assertOk();

        $ids = array_map(fn ($f) => $f['c'][0][0], $respuesta->json('fotos'));
        $this->assertSame([1, 3, 2], $ids);
        $this->assertCount(3, $respuesta->json('fotos'));
    }

    public function test_cada_colectivo_viaja_como_una_lista_compacta_y_el_estado_se_explica_aparte(): void
    {
        $this->foto('2026-10-05 12:00:00', [['id' => 7, 'ramal' => 3, 'lon' => -60.5167, 'lat' => -31.7369, 'rumbo' => -80, 'estado' => 'demorado']]);

        $respuesta = $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:01:00Z')->assertOk();

        $this->assertSame([7, 3, -60.5167, -31.7369, -80, 3], $respuesta->json('fotos.0.c.0'));
        $this->assertSame(Instantanea::ESTADOS, $respuesta->json('estados'));
        $this->assertSame('demorado', $respuesta->json('estados')[3]);
        $this->assertSame(Carbon::parse('2026-10-05 12:00:00', 'UTC')->getTimestamp(), $respuesta->json('fotos.0.t'));
    }

    public function test_los_extremos_de_la_ventana_se_incluyen(): void
    {
        $this->foto('2026-10-05 12:00:00', [$this->unColectivo()]);
        $this->foto('2026-10-05 12:01:00', [$this->unColectivo()]);

        $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:01:00Z')->assertJsonCount(2, 'fotos');
    }

    public function test_una_ventana_sin_fotos_devuelve_una_lista_vacia(): void
    {
        $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:05:00Z')
            ->assertOk()
            ->assertJsonCount(0, 'fotos');
    }

    public function test_no_se_puede_pedir_una_ventana_mas_larga_que_el_maximo(): void
    {
        $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:16:00Z')
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Se puede pedir como mucho una ventana de 15 minutos por vez.');

        $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:15:00Z')->assertOk();
    }

    public function test_faltan_o_sobran_datos_en_el_pedido_y_se_avisa_cual(): void
    {
        $this->getJson('/api/historial')->assertStatus(422)->assertJsonValidationErrors(['desde', 'hasta']);
        $this->getJson('/api/historial?desde=nunca&hasta=2026-10-05T12:00:00Z')->assertStatus(422)->assertJsonValidationErrors(['desde']);
        $this->getJson('/api/historial?desde=2026-10-05T12:05:00Z&hasta=2026-10-05T12:00:00Z')
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', 'La fecha de fin tiene que ser posterior a la de inicio.');
    }

    public function test_lo_que_ya_paso_se_puede_guardar_en_cache_y_lo_reciente_no_por_mucho_tiempo(): void
    {
        Carbon::setTestNow('2026-10-05 15:00:00');

        $pasado = $this->getJson('/api/historial?desde=2026-10-05T12:00:00Z&hasta=2026-10-05T12:05:00Z');
        $reciente = $this->getJson('/api/historial?desde=2026-10-05T14:55:00Z&hasta=2026-10-05T15:00:00Z');

        $this->assertStringContainsString('max-age=300', $pasado->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=2', $reciente->headers->get('Cache-Control'));
    }

    // ---------- retención ----------

    public function test_la_limpieza_borra_lo_que_pasa_de_la_retencion_y_deja_lo_reciente(): void
    {
        Carbon::setTestNow('2026-10-05 15:00:00');
        $this->foto('2026-10-03 10:00:00');  // hace 53 h
        $this->foto('2026-10-05 05:00:00');  // hace 10 h
        $this->foto('2026-10-05 14:00:00');  // hace 1 h

        $this->artisan('ramal:limpiar-historial')->assertExitCode(0);

        $this->assertSame(2, HistorialPosicion::count());
        $this->assertSame(0, HistorialPosicion::where('momento', '<', '2026-10-03 15:00:00')->count());
    }

    public function test_se_puede_elegir_cuantas_horas_conservar(): void
    {
        Carbon::setTestNow('2026-10-05 15:00:00');
        $this->foto('2026-10-05 05:00:00');
        $this->foto('2026-10-05 14:00:00');

        $this->artisan('ramal:limpiar-historial', ['--horas' => 2])->assertExitCode(0);

        $this->assertSame(1, HistorialPosicion::count());
    }

    public function test_el_historial_no_crece_sin_limite_con_la_limpieza_programada(): void
    {
        Carbon::setTestNow('2026-10-05 15:00:00');
        // Tres días de fotos cada hora.
        foreach (range(0, 72) as $horas) {
            $this->foto(now()->copy()->subHours($horas)->toDateTimeString());
        }

        $this->artisan('ramal:limpiar-historial')->assertExitCode(0);

        $this->assertLessThanOrEqual(49, HistorialPosicion::count(), 'Quedan a lo sumo las 48 horas de retención');
    }

    public function test_la_limpieza_esta_programada_cada_hora(): void
    {
        $tareas = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command, 'ramal:limpiar-historial'));

        $this->assertCount(1, $tareas);
        $this->assertSame('0 * * * *', $tareas->first()->expression);
    }

    // ---------- rehacer el día ----------

    public function test_rehacer_el_dia_deja_historial_incidentes_y_posiciones_coherentes_sin_emitir_nada(): void
    {
        Event::fake([PosicionesActualizadas::class]);
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', self::ZONA));
        $viejo = Incidente::create(['colectivo_id' => Colectivo::first()->id, 'tipo' => 'demora', 'duracion_prevista_s' => 60, 'inicio_en' => now()->subHour(), 'origen' => 'simulacion']);

        $this->artisan('ramal:rellenar-dia', ['--desde' => '09:30', '--paso' => 60, '--forzar' => true])->assertExitCode(0);

        $this->assertSame(30, HistorialPosicion::count());
        $this->assertSame(40, Posicion::count());
        $this->assertSame(30, Simulacion::actual()->tick);
        $this->assertNull(Incidente::find($viejo->id), 'Los incidentes de hoy se rehacen');
        $this->assertSame(0, Incidente::where('origen', 'simulacion')->count());
        Event::assertNotDispatched(PosicionesActualizadas::class);

        $primera = HistorialPosicion::orderBy('momento')->first();
        $ultima = HistorialPosicion::orderByDesc('momento')->first();
        $this->assertSame('2026-10-05 12:31:00', $primera->momento->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 13:00:00', $ultima->momento->format('Y-m-d H:i:s'));
    }

    public function test_rehacer_solo_los_ultimos_minutos_es_lo_que_usa_el_contenedor_al_despertar(): void
    {
        Event::fake([PosicionesActualizadas::class]);
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', self::ZONA));

        $this->artisan('ramal:rellenar-dia', ['--ultimos' => 20, '--paso' => 60, '--forzar' => true])->assertExitCode(0);

        $this->assertSame(20, HistorialPosicion::count());
        $this->assertSame(40, Posicion::count());

        $primera = HistorialPosicion::orderBy('momento')->first();
        $ultima = HistorialPosicion::orderByDesc('momento')->first();
        $this->assertSame('2026-10-05 12:41:00', $primera->momento->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 13:00:00', $ultima->momento->format('Y-m-d H:i:s'));
    }

    public function test_rehacer_el_dia_se_niega_si_la_simulacion_en_vivo_esta_corriendo(): void
    {
        Simulacion::actual()->update(['ultimo_tick_en' => now()]);

        $this->artisan('ramal:rellenar-dia', ['--desde' => '00:00'])
            ->expectsOutputToContain('La simulación en vivo está corriendo')
            ->assertExitCode(1);

        $this->assertSame(0, HistorialPosicion::count());
    }

    public function test_la_simulacion_en_vivo_sigue_despues_de_rehacer_el_dia_sin_saltos_ni_duplicados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', self::ZONA));
        $this->artisan('ramal:rellenar-dia', ['--desde' => '09:30', '--paso' => 60, '--forzar' => true])->assertExitCode(0);
        $antes = HistorialPosicion::count();
        $posicionesAntes = Posicion::pluck('distancia_m', 'colectivo_id')->all();

        $servicio = new ServicioSimulacion;
        $servicio->tick(2.0, now()->addSeconds(2));

        $this->assertSame($antes, HistorialPosicion::count(), 'Dos segundos después de la última foto no hay otra');
        $despues = Posicion::pluck('distancia_m', 'colectivo_id')->all();
        foreach ($despues as $id => $d) {
            $this->assertEqualsWithDelta($posicionesAntes[$id], $d, 40, 'Ningún colectivo salta de lugar al retomar');
        }
    }
}
