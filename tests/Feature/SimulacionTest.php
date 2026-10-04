<?php

namespace Tests\Feature;

use App\Events\PosicionesActualizadas;
use App\Models\Colectivo;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\Simulacion;
use App\Simulacion\Celdas;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SimulacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);
    }

    private function servicio(): ServicioSimulacion
    {
        $servicio = new ServicioSimulacion;
        $servicio->preparar();

        return $servicio;
    }

    public function test_preparar_pone_a_los_cuarenta_colectivos_en_sus_recorridos(): void
    {
        $this->servicio();

        $this->assertSame(40, Posicion::count());
        $this->assertSame(0, Posicion::where('estado', EstadoColectivo::FUERA_DE_SERVICIO)->count());

        foreach (Posicion::all() as $p) {
            $this->assertEqualsWithDelta(-31.74, $p->latitud, 0.05);
            $this->assertEqualsWithDelta(-60.52, $p->longitud, 0.07);
        }
    }

    public function test_preparar_no_duplica_ni_pisa_lo_que_ya_estaba(): void
    {
        $servicio = $this->servicio();
        $servicio->tick(2.0);
        $antes = Posicion::orderBy('colectivo_id')->pluck('distancia_m', 'colectivo_id')->all();

        $servicio->preparar();

        $this->assertSame(40, Posicion::count());
        $this->assertSame($antes, Posicion::orderBy('colectivo_id')->pluck('distancia_m', 'colectivo_id')->all());
    }

    public function test_un_tick_avanza_el_contador_y_mueve_a_los_colectivos(): void
    {
        $servicio = $this->servicio();
        $antes = Posicion::pluck('distancia_m', 'colectivo_id')->all();

        $servicio->tick(2.0);
        $servicio->tick(2.0);

        $this->assertSame(2, Simulacion::actual()->tick);
        $despues = Posicion::pluck('distancia_m', 'colectivo_id')->all();
        $movidos = collect($despues)->filter(fn ($d, $id) => $d != $antes[$id])->count();
        $this->assertGreaterThan(30, $movidos);
        $this->assertSame(2, Posicion::first()->tick);
    }

    public function test_lo_que_se_emite_va_por_celda_sin_repetir_ni_perder_colectivos(): void
    {
        Event::fake([PosicionesActualizadas::class]);
        $servicio = $this->servicio();

        $servicio->tick(2.0);

        $celdas = Celdas::desdeConfiguracion();
        $vistos = [];

        Event::assertDispatched(PosicionesActualizadas::class);
        Event::assertDispatched(PosicionesActualizadas::class, function (PosicionesActualizadas $evento) use ($celdas, &$vistos) {
            foreach ($evento->colectivos as $c) {
                // Cada colectivo viaja solo por la celda donde está.
                $this->assertSame($evento->celda, $celdas->de(...$this->ultimoPunto($c)), "Colectivo {$c['interno']} en la celda equivocada");
                $vistos[] = $c['id'];
            }

            return $evento->tick === 1 && $evento->broadcastAs() === 'actualizacion';
        });

        $this->assertCount(40, $vistos);
        $this->assertCount(40, array_unique($vistos));
    }

    public function test_el_canal_de_cada_evento_es_el_de_su_celda(): void
    {
        $evento = new PosicionesActualizadas('2.1', 7, []);

        $this->assertSame('posiciones.2.1', $evento->broadcastOn()->name);
        $this->assertSame(['tick' => 7, 'celda' => '2.1', 'colectivos' => []], $evento->broadcastWith());
    }

    public function test_el_mensaje_trae_el_trazado_recorrido_para_que_el_navegador_doble_en_las_esquinas(): void
    {
        Event::fake([PosicionesActualizadas::class]);
        $servicio = $this->servicio();
        $servicio->tick(2.0);

        $emitidos = collect($servicio->tick(2.0))->flatten(1);

        $this->assertCount(40, $emitidos);
        foreach ($emitidos as $c) {
            $this->assertNotEmpty($c['ruta']);
            $this->assertContainsOnly('array', $c['ruta']);
            $this->assertCount(2, $c['ruta'][0]);
            $this->assertArrayHasKey('rumbo', $c);
            $this->assertArrayHasKey('estado', $c);
        }

        $this->assertGreaterThan(40, $emitidos->sum(fn ($c) => count($c['ruta'])), 'Alguno recorrió más de un tramo y trae esquinas');
    }

    public function test_los_incidentes_se_guardan_y_se_resuelven(): void
    {
        $servicio = $this->servicio();

        // Dos horas simuladas en 60 ticks de dos minutos: alcanza para ver incidentes sin esperar.
        for ($i = 0; $i < 60; $i++) {
            $servicio->tick(120.0);
        }

        $this->assertGreaterThan(0, Incidente::count());
        $this->assertGreaterThan(0, Incidente::where('estado', 'resuelto')->count());
        $this->assertSame(0, Incidente::where('estado', 'resuelto')->whereNull('fin_en')->count());
        $this->assertGreaterThan(0, Incidente::where('estado', 'activo')->count() + Incidente::where('estado', 'resuelto')->count());
    }

    public function test_misma_semilla_misma_simulacion(): void
    {
        $this->servicio();
        $servicio = new ServicioSimulacion;
        for ($i = 0; $i < 50; $i++) {
            $servicio->tick(2.0);
        }
        $primera = Posicion::orderBy('colectivo_id')->get(['colectivo_id', 'ramal_id', 'distancia_m', 'estado'])->toArray();

        Posicion::query()->delete();
        Simulacion::actual()->update(['tick' => 0]);
        $otro = new ServicioSimulacion;
        $otro->preparar();
        for ($i = 0; $i < 50; $i++) {
            $otro->tick(2.0);
        }
        $segunda = Posicion::orderBy('colectivo_id')->get(['colectivo_id', 'ramal_id', 'distancia_m', 'estado'])->toArray();

        $this->assertSame($primera, $segunda);
    }

    public function test_fuera_del_horario_de_servicio_no_hay_colectivos_en_la_calle(): void
    {
        config(['ramal.siempre_en_servicio' => false]);
        $servicio = $this->servicio();

        $servicio->tick(2.0, Carbon::parse('2026-10-05 03:00:00', 'America/Argentina/Buenos_Aires'));  // lunes de madrugada

        $this->assertSame(40, Posicion::where('estado', EstadoColectivo::FUERA_DE_SERVICIO)->count());
        $this->assertSame([], $servicio->instantanea());
    }

    public function test_al_empezar_el_servicio_salen_de_a_uno_desde_la_terminal(): void
    {
        config(['ramal.siempre_en_servicio' => false]);
        $servicio = $this->servicio();
        $servicio->tick(2.0, Carbon::parse('2026-10-05 03:00:00', 'America/Argentina/Buenos_Aires'));

        $servicio->tick(2.0, Carbon::parse('2026-10-05 10:00:00', 'America/Argentina/Buenos_Aires'));

        $this->assertSame(0, Posicion::where('estado', EstadoColectivo::FUERA_DE_SERVICIO)->count());
        // El primero de cada línea sale enseguida; los otros siete esperan su turno en la terminal.
        $this->assertSame(35, Posicion::where('distancia_m', 0)->count());
        $esperas = Posicion::where('espera_s', '>', 0)->pluck('espera_s')->unique()->count();
        $this->assertGreaterThan(5, $esperas, 'Cada uno sale a una hora distinta');
    }

    public function test_el_sabado_rige_el_horario_del_sabado(): void
    {
        config(['ramal.siempre_en_servicio' => false]);
        $servicio = $this->servicio();
        $colectivo = Colectivo::with('linea')->first();

        // El sábado el servicio arranca a las 06:00; los días hábiles, a las 05:30.
        $this->assertFalse($servicio->enServicio($colectivo, Carbon::parse('2026-10-10 05:45:00', 'America/Argentina/Buenos_Aires')));
        $this->assertTrue($servicio->enServicio($colectivo, Carbon::parse('2026-10-09 05:45:00', 'America/Argentina/Buenos_Aires')));
    }

    /** @return array{0: float, 1: float} */
    private function ultimoPunto(array $colectivo): array
    {
        return $colectivo['ruta'][array_key_last($colectivo['ruta'])];
    }
}
