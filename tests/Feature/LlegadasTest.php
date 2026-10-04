<?php

namespace Tests\Feature;

use App\Estimaciones\ServicioLlegadas;
use App\Models\Parada;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LlegadasTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Argentina/Buenos_Aires';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);
        $servicio = new ServicioSimulacion;
        $servicio->preparar();
        $servicio->tick(2.0);
    }

    private function plaza(): Parada
    {
        return Parada::where('nombre', 'Plaza 1° de Mayo')->firstOrFail();
    }

    public function test_la_api_devuelve_los_proximos_colectivos_ordenados_por_cuanto_faltan(): void
    {
        $respuesta = $this->getJson("/api/paradas/{$this->plaza()->id}/llegadas")->assertOk();

        $respuesta->assertJsonPath('parada.nombre', 'Plaza 1° de Mayo')
            ->assertJsonPath('servicio', 'en_servicio');

        $llegadas = $respuesta->json('llegadas');
        $this->assertNotEmpty($llegadas);

        $segundos = array_column($llegadas, 'segundos');
        $ordenados = $segundos;
        sort($ordenados);
        $this->assertSame($ordenados, $segundos);

        foreach ($llegadas as $llegada) {
            $this->assertEqualsCanonicalizing(
                ['linea', 'ramal_id', 'destino', 'interno', 'segundos', 'incierta', 'estado'],
                array_keys($llegada),
            );
            $this->assertGreaterThanOrEqual(0, $llegada['segundos']);
        }
    }

    public function test_de_cada_ramal_se_muestran_como_mucho_dos_colectivos(): void
    {
        $llegadas = $this->getJson("/api/paradas/{$this->plaza()->id}/llegadas")->json('llegadas');

        foreach (collect($llegadas)->groupBy('ramal_id') as $deUnRamal) {
            $this->assertLessThanOrEqual(ServicioLlegadas::POR_RAMAL, $deUnRamal->count());
        }
    }

    public function test_pasan_todas_las_lineas_que_tienen_la_parada_en_su_recorrido(): void
    {
        $lineas = collect($this->getJson("/api/paradas/{$this->plaza()->id}/llegadas")->json('llegadas'))->pluck('linea')->unique()->sort()->values()->all();

        $this->assertSame([1, 2, 3, 4, 5], $lineas);
    }

    public function test_no_se_anuncian_colectivos_que_terminan_su_recorrido_en_esa_parada(): void
    {
        $terminal = Parada::where('nombre', 'Terminal de Ómnibus')->firstOrFail();

        $llegadas = $this->getJson("/api/paradas/{$terminal->id}/llegadas")->json('llegadas');

        foreach ($llegadas as $llegada) {
            $this->assertNotSame('Terminal de Ómnibus', $llegada['destino'], 'Nadie se sube a un colectivo que termina acá');
        }
    }

    public function test_una_parada_que_no_existe_devuelve_404(): void
    {
        $this->getJson('/api/paradas/9999/llegadas')->assertNotFound();
    }

    public function test_el_ultimo_colectivo_del_dia_no_se_anuncia_si_llega_despues_de_que_termina_el_servicio(): void
    {
        config(['ramal.siempre_en_servicio' => false]);
        $servicio = app(ServicioSimulacion::class);
        $servicio->tick(2.0, Carbon::parse('2026-10-05 10:00:00', self::ZONA)); // lunes: empieza el servicio

        // A las 23:25 el servicio termina a las 23:30: solo cuentan los colectivos que llegan en menos de 5 minutos.
        $respuesta = app(ServicioLlegadas::class)->paraParada($this->plaza(), Carbon::parse('2026-10-05 23:25:00', self::ZONA));

        foreach ($respuesta['llegadas'] as $llegada) {
            $this->assertLessThanOrEqual(300, $llegada['segundos']);
        }
    }

    public function test_pasada_la_hora_de_fin_no_hay_llegadas_y_se_avisa_cuando_vuelve_el_servicio(): void
    {
        config(['ramal.siempre_en_servicio' => false]);
        app(ServicioSimulacion::class)->tick(2.0, Carbon::parse('2026-10-05 10:00:00', self::ZONA));

        $respuesta = app(ServicioLlegadas::class)->paraParada($this->plaza(), Carbon::parse('2026-10-05 23:45:00', self::ZONA));

        $this->assertSame([], $respuesta['llegadas']);
        $this->assertSame('sin_servicio', $respuesta['servicio']);
        $this->assertSame('05:30', $respuesta['proximo_servicio']);
    }

    public function test_con_el_servicio_en_marcha_dice_en_servicio_y_no_anuncia_proximo_inicio(): void
    {
        $respuesta = app(ServicioLlegadas::class)->paraParada($this->plaza());

        $this->assertSame('en_servicio', $respuesta['servicio']);
        $this->assertNull($respuesta['proximo_servicio']);
    }
}
