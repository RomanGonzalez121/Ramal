<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CiudadSeeder;
use Database\Seeders\OperadorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelOperadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([CiudadSeeder::class, OperadorSeeder::class]);
    }

    private function operador(): User
    {
        return User::where('email', OperadorSeeder::CORREO_DEMO)->firstOrFail();
    }

    private function visitante(): User
    {
        return User::factory()->create(['rol' => 'visitante', 'password' => 'secreta-123']);
    }

    public function test_quien_no_ingreso_es_llevado_a_la_pantalla_de_ingreso(): void
    {
        $this->get('/operador')->assertRedirect(route('login'));
        $this->get('/operador/resumen')->assertRedirect(route('login'));
    }

    public function test_los_datos_del_panel_no_se_entregan_a_quien_no_ingreso(): void
    {
        $this->getJson('/operador/resumen')->assertUnauthorized();
    }

    public function test_una_cuenta_que_no_es_de_operador_no_puede_ver_el_panel(): void
    {
        $this->actingAs($this->visitante())->get('/operador')->assertForbidden();
        $this->actingAs($this->visitante())->getJson('/operador/resumen')->assertForbidden();
    }

    public function test_un_operador_ve_el_panel_y_sus_datos(): void
    {
        $this->actingAs($this->operador())
            ->get('/operador')
            ->assertOk()
            ->assertSee('Centro de control')
            ->assertSee('Operadora de demostración');

        $this->actingAs($this->operador())
            ->getJson('/operador/resumen')
            ->assertOk()
            ->assertJsonStructure(['generado_en', 'indicadores', 'atencion', 'incidentes', 'graficos' => ['por_hora', 'por_linea', 'por_tipo'], 'hora_actual'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_la_pantalla_de_ingreso_se_muestra_con_la_cuenta_de_demostracion(): void
    {
        config(['ramal.mostrar_cuenta_demo' => true]);

        $this->get('/operador/ingresar')
            ->assertOk()
            ->assertSee('Cuenta de demostración')
            ->assertSee(OperadorSeeder::CORREO_DEMO);
    }

    public function test_la_cuenta_de_demostracion_se_puede_ocultar(): void
    {
        config(['ramal.mostrar_cuenta_demo' => false]);

        $this->get('/operador/ingresar')
            ->assertOk()
            ->assertDontSee('Cuenta de demostración')
            ->assertDontSee(OperadorSeeder::CLAVE_DEMO);
    }

    public function test_un_operador_puede_ingresar_y_llega_al_panel(): void
    {
        $this->post('/operador/ingresar', ['email' => OperadorSeeder::CORREO_DEMO, 'password' => OperadorSeeder::CLAVE_DEMO])
            ->assertRedirect(route('operador'));

        $this->assertAuthenticatedAs($this->operador());
    }

    public function test_despues_de_ingresar_vuelve_a_donde_queria_ir(): void
    {
        $this->get('/operador')->assertRedirect(route('login'));

        $this->post('/operador/ingresar', ['email' => OperadorSeeder::CORREO_DEMO, 'password' => OperadorSeeder::CLAVE_DEMO])
            ->assertRedirect('/operador');
    }

    public function test_una_contrasena_equivocada_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->from('/operador/ingresar')
            ->post('/operador/ingresar', ['email' => OperadorSeeder::CORREO_DEMO, 'password' => 'incorrecta'])
            ->assertRedirect('/operador/ingresar')
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no coinciden con una cuenta de operador.']);

        $this->assertGuest();
    }

    public function test_una_cuenta_sin_rol_de_operador_no_ingresa_aunque_la_contrasena_sea_correcta(): void
    {
        $visitante = $this->visitante();

        $this->post('/operador/ingresar', ['email' => $visitante->email, 'password' => 'secreta-123'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no coinciden con una cuenta de operador.']);

        $this->assertGuest();
    }

    public function test_faltan_datos_y_se_avisa_que_campo_es(): void
    {
        $this->post('/operador/ingresar', [])
            ->assertSessionHasErrors(['email' => 'Escribí tu correo.', 'password' => 'Escribí tu contraseña.']);
    }

    public function test_despues_de_cinco_intentos_seguidos_se_frena(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/operador/ingresar', ['email' => OperadorSeeder::CORREO_DEMO, 'password' => 'mala-'.$i]);
        }

        $this->post('/operador/ingresar', ['email' => OperadorSeeder::CORREO_DEMO, 'password' => OperadorSeeder::CLAVE_DEMO])
            ->assertStatus(429);
        $this->assertGuest();
    }

    public function test_salir_cierra_la_sesion_y_vuelve_al_mapa(): void
    {
        $this->actingAs($this->operador())
            ->post('/operador/salir')
            ->assertRedirect(route('mapa'));

        $this->assertGuest();
    }

    public function test_la_contrasena_nunca_se_guarda_en_claro(): void
    {
        $guardada = $this->operador()->getRawOriginal('password');

        $this->assertNotSame(OperadorSeeder::CLAVE_DEMO, $guardada);
        $this->assertStringStartsWith('$2y$', $guardada);
    }

    public function test_el_sembrador_se_puede_correr_dos_veces_sin_duplicar_la_cuenta(): void
    {
        $this->seed(OperadorSeeder::class);

        $this->assertSame(1, User::where('email', OperadorSeeder::CORREO_DEMO)->count());
    }
}
