<?php

namespace Tests\Feature;

use App\Models\Colectivo;
use App\Models\Incidente;
use App\Models\Posicion;
use App\Models\User;
use App\Operacion\Resumen;
use App\Simulacion\EstadoColectivo;
use App\Simulacion\ServicioSimulacion;
use Database\Seeders\CiudadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResumenOperadorTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Argentina/Buenos_Aires';

    private Carbon $ahora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CiudadSeeder::class);
        config(['ramal.siempre_en_servicio' => true]);

        // Domingo 4 de octubre de 2026, 15:30 en Paraná.
        $this->ahora = Carbon::parse('2026-10-04 15:30:00', self::ZONA);
        $servicio = new ServicioSimulacion;
        $servicio->preparar();
    }

    private function resumen(): array
    {
        return app(Resumen::class)->calcular($this->ahora);
    }

    private function incidente(string $interno, string $tipo, string $hora, ?string $fin = null, string $dia = '2026-10-04'): Incidente
    {
        $colectivo = Colectivo::where('interno', $interno)->firstOrFail();

        return Incidente::create([
            'colectivo_id' => $colectivo->id,
            'tipo' => $tipo,
            'estado' => $fin ? 'resuelto' : 'activo',
            'duracion_prevista_s' => 300,
            'inicio_en' => Carbon::parse("{$dia} {$hora}", self::ZONA)->utc(),
            'fin_en' => $fin ? Carbon::parse("{$dia} {$fin}", self::ZONA)->utc() : null,
        ]);
    }

    public function test_sin_incidentes_todo_anda_bien(): void
    {
        $r = $this->resumen();

        $this->assertSame(40, $r['indicadores']['en_servicio']);
        $this->assertSame(100, $r['indicadores']['en_hora_pct']);
        $this->assertSame(0, $r['indicadores']['incidentes_del_dia']);
        $this->assertSame([], $r['atencion']);
        $this->assertSame(array_fill(0, 24, 0), $r['graficos']['por_hora']);
        $this->assertSame(15, $r['hora_actual']);
    }

    public function test_los_incidentes_se_cuentan_en_la_hora_de_parana_y_no_en_la_del_servidor(): void
    {
        $this->incidente('101', 'demora', '03:10');   // en UTC ya sería las 06
        $this->incidente('102', 'demora', '03:50');
        $this->incidente('201', 'falla', '14:05');

        $horas = $this->resumen()['graficos']['por_hora'];

        $this->assertSame(2, $horas[3]);
        $this->assertSame(1, $horas[14]);
        $this->assertSame(0, $horas[6]);
        $this->assertSame(3, array_sum($horas));
    }

    public function test_el_dia_arranca_a_la_medianoche_de_parana(): void
    {
        $this->incidente('101', 'demora', '23:50', '23:55', '2026-10-03');  // ayer, 02:50 UTC de hoy
        $this->incidente('102', 'demora', '00:10', '00:20');                 // hoy, 03:10 UTC

        $r = $this->resumen();

        $this->assertSame(1, $r['indicadores']['incidentes_del_dia']);
        $this->assertSame(1, $r['graficos']['por_hora'][0]);
    }

    public function test_los_incidentes_se_cuentan_por_linea_aunque_alguna_no_tenga(): void
    {
        $this->incidente('301', 'demora', '10:00', '10:05');
        $this->incidente('302', 'falla', '11:00', '11:10');
        $this->incidente('101', 'desvio', '12:00', '12:08');

        $porLinea = collect($this->resumen()['graficos']['por_linea'])->pluck('cantidad', 'linea')->all();

        $this->assertSame([1 => 1, 2 => 0, 3 => 2, 4 => 0, 5 => 0], $porLinea);
    }

    public function test_por_tipo_calcula_la_duracion_promedio_en_minutos(): void
    {
        $this->incidente('101', 'demora', '10:00', '10:04');
        $this->incidente('102', 'demora', '11:00', '11:08');
        $this->incidente('201', 'falla', '12:00', '12:10');

        $porTipo = collect($this->resumen()['graficos']['por_tipo'])->keyBy('tipo');

        $this->assertSame(2, $porTipo['demora']['cantidad']);
        $this->assertSame(6, $porTipo['demora']['minutos_promedio']);
        $this->assertSame(10, $porTipo['falla']['minutos_promedio']);
        $this->assertSame(0, $porTipo['desvio']['cantidad']);
        $this->assertSame(0, $porTipo['desvio']['minutos_promedio']);
    }

    public function test_la_duracion_de_un_incidente_nunca_es_negativa(): void
    {
        $this->incidente('101', 'demora', '10:00', '10:07');
        $this->incidente('102', 'falla', '15:10');  // sigue abierto: dura hasta ahora

        $duraciones = collect($this->resumen()['incidentes'])->pluck('duracion_min', 'interno');

        $this->assertSame(7, $duraciones['101']);
        $this->assertSame(20, $duraciones['102']);
    }

    public function test_los_incidentes_abiertos_aparecen_primero_y_despues_los_mas_recientes(): void
    {
        $this->incidente('101', 'demora', '09:00', '09:05');
        $this->incidente('102', 'demora', '14:00', '14:05');
        $this->incidente('103', 'falla', '08:00');          // abierto, aunque viejo
        $this->incidente('104', 'desvio', '15:00');         // abierto, más nuevo

        $internos = collect($this->resumen()['incidentes'])->pluck('interno')->all();

        $this->assertSame(['104', '103', '102', '101'], $internos);
    }

    public function test_el_listado_no_pasa_del_limite(): void
    {
        foreach (range(1, Resumen::LISTADO + 10) as $n) {
            $this->incidente('101', 'demora', sprintf('%02d:%02d', intdiv($n, 60) + 1, $n % 60), sprintf('%02d:%02d', intdiv($n, 60) + 1, $n % 60));
        }

        $this->assertCount(Resumen::LISTADO, $this->resumen()['incidentes']);
    }

    public function test_los_colectivos_demorados_aparecen_con_su_ubicacion_y_hace_cuanto(): void
    {
        $colectivo = Colectivo::where('interno', '301')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::DEMORADO, 'incidente_tipo' => 'demora', 'ultima_parada' => 2]);
        $this->incidente('301', 'demora', '15:20');

        $r = $this->resumen();

        $this->assertCount(1, $r['atencion']);
        $this->assertSame('301', $r['atencion'][0]['interno']);
        $this->assertSame(3, $r['atencion'][0]['linea']);
        $this->assertSame('demorado', $r['atencion'][0]['estado']);
        $this->assertSame(10, $r['atencion'][0]['minutos']);
        $this->assertStringStartsWith('Entre ', $r['atencion'][0]['ubicacion']);

        $this->assertSame(1, $r['indicadores']['demorados']);
        $this->assertSame(1, $r['indicadores']['incidentes_activos']);
        $this->assertSame(98, $r['indicadores']['en_hora_pct']);   // 39 de 40 sin problema, redondeado
    }

    public function test_los_colectivos_fuera_de_servicio_no_cuentan_como_en_servicio(): void
    {
        Posicion::whereIn('colectivo_id', Colectivo::where('interno', 'like', '1%')->pluck('id'))
            ->update(['estado' => EstadoColectivo::FUERA_DE_SERVICIO]);

        $this->assertSame(32, $this->resumen()['indicadores']['en_servicio']);
    }

    public function test_cada_colectivo_con_problemas_trae_su_incidente_y_lo_que_se_puede_hacer(): void
    {
        $colectivo = Colectivo::where('interno', '301')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::DEMORADO, 'incidente_tipo' => 'demora']);
        $incidente = $this->incidente('301', 'demora', '15:20');

        $item = $this->resumen()['atencion'][0];

        $this->assertSame($incidente->id, $item['incidente']['id']);
        $this->assertSame('activo', $item['incidente']['estado']);
        $this->assertTrue($item['incidente']['puede_atender']);
        $this->assertTrue($item['incidente']['puede_resolver']);
        $this->assertFalse($item['incidente']['resolviendo']);
        $this->assertNull($item['incidente']['atendido_por']);
    }

    public function test_un_desvio_se_puede_atender_pero_no_resolver_a_mano(): void
    {
        $colectivo = Colectivo::where('interno', '302')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::FUERA_DE_RECORRIDO, 'incidente_tipo' => 'desvio']);
        $this->incidente('302', 'desvio', '15:10');

        $r = $this->resumen();
        $item = $r['atencion'][0];

        $this->assertSame('fuera_de_recorrido', $item['estado']);
        $this->assertTrue($item['incidente']['puede_atender']);
        $this->assertFalse($item['incidente']['puede_resolver']);
        $this->assertSame(1, $r['indicadores']['fuera_de_recorrido']);
    }

    public function test_un_incidente_atendido_ya_no_se_puede_atender_de_nuevo_y_dice_quien_lo_tomo(): void
    {
        $colectivo = Colectivo::where('interno', '303')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::AVERIADO, 'incidente_tipo' => 'falla']);
        $incidente = $this->incidente('303', 'falla', '15:00');
        $operadora = User::factory()->create(['name' => 'Marta Gómez', 'rol' => 'operador']);
        $incidente->update(['estado' => 'atendido', 'atendido_por' => $operadora->id, 'atendido_en' => $this->ahora->copy()->utc()]);

        $item = $this->resumen()['atencion'][0]['incidente'];

        $this->assertFalse($item['puede_atender']);
        $this->assertSame('Marta Gómez', $item['atendido_por']);
        $this->assertSame('atendido', $item['estado']);
    }

    public function test_si_ya_se_pidio_resolverlo_el_panel_lo_muestra_como_en_curso(): void
    {
        $colectivo = Colectivo::where('interno', '304')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::AVERIADO, 'incidente_tipo' => 'falla']);
        $this->incidente('304', 'falla', '15:00')->update(['accion_pedida' => 'resolver']);

        $item = $this->resumen()['atencion'][0]['incidente'];

        $this->assertTrue($item['resolviendo']);
        $this->assertFalse($item['puede_resolver'], 'No se puede pedir dos veces');
    }

    public function test_un_colectivo_con_falla_se_cuenta_como_averiado(): void
    {
        $colectivo = Colectivo::where('interno', '202')->firstOrFail();
        Posicion::where('colectivo_id', $colectivo->id)->update(['estado' => EstadoColectivo::AVERIADO, 'incidente_tipo' => 'falla']);

        $r = $this->resumen();

        $this->assertSame(1, $r['indicadores']['averiados']);
        $this->assertSame('averiado', $r['atencion'][0]['estado']);
    }
}
