<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_chofer_puede_iniciar_sesion_y_usar_el_token(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);

        $respuesta = $this->postJson('/api/login', ['email' => $chofer->email, 'password' => 'password']);

        $respuesta->assertOk()->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.id', $chofer->id);
        $this->withToken($respuesta->json('token'))->getJson('/api/entregas')->assertOk();
    }

    public function test_credenciales_incorrectas_y_correo_inexistente_comparten_el_mismo_error(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);
        $incorrecto = $this->postJson('/api/login', ['email' => $chofer->email, 'password' => 'incorrecta']);
        $inexistente = $this->postJson('/api/login', ['email' => 'nadie@example.com', 'password' => 'incorrecta']);

        $incorrecto->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
        $inexistente->assertUnprocessable()->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');
    }

    public function test_rechaza_chofer_inactivo_y_admin_con_mensajes_especificos(): void
    {
        $inactivo = User::factory()->chofer()->inactivo()->create(['password' => 'password']);
        $this->postJson('/api/login', ['email' => $inactivo->email, 'password' => 'password'])->assertForbidden()
            ->assertExactJson(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
        $this->postJson('/api/login', ['email' => $inactivo->email, 'password' => 'incorrecta'])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Las credenciales no son correctas.');

        $admin = User::factory()->create(['password' => 'password']);
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertForbidden()
            ->assertExactJson(['message' => 'Esta cuenta no tiene acceso a la app.']);
    }

    public function test_valida_campos_del_login_en_espanol(): void
    {
        foreach ([
            [[], 'email'],
            [['email' => 'chofer@example.com'], 'password'],
            [['email' => 'correo-invalido', 'password' => 'password'], 'email'],
            [['email' => 'chofer@example.com', 'password' => 'password', 'device_name' => str_repeat('x', 61)], 'device_name'],
        ] as [$datos, $campo]) {
            $this->postJson('/api/login', $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
    }

    public function test_device_name_nulo_usa_el_nombre_app(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);

        $this->postJson('/api/login', [
            'email' => $chofer->email,
            'password' => 'password',
            'device_name' => null,
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $chofer->id,
            'name' => 'app',
        ]);
    }

    public function test_correo_enviado_como_arreglo_responde_error_de_validacion(): void
    {
        $this->postJson('/api/login', [
            'email' => ['chofer@example.com'],
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_reemplaza_token_del_mismo_dispositivo_y_conserva_los_demas(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);
        $primero = $this->login($chofer, 'telefono');
        $otro = $this->login($chofer, 'tablet');
        $nuevo = $this->login($chofer, 'telefono');

        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->peticionLimpia(fn () => $this->withToken($primero)->getJson('/api/entregas')->assertUnauthorized());
        $this->peticionLimpia(fn () => $this->withToken($otro)->getJson('/api/entregas')->assertOk());
        $this->peticionLimpia(fn () => $this->withToken($nuevo)->getJson('/api/entregas')->assertOk());
    }

    public function test_limita_a_cinco_intentos_por_minuto(): void
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/login', ['email' => 'chofer@example.com', 'password' => 'incorrecta'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'chofer@example.com', 'password' => 'incorrecta'])->assertStatus(429)
            ->assertExactJson(['message' => 'Demasiados intentos. Intenta de nuevo en un minuto.']);
    }

    public function test_logout_revoca_solo_el_token_actual_y_requiere_autenticacion(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);
        $token = $this->login($chofer, 'telefono');
        $otroToken = $this->login($chofer, 'tablet');

        $this->peticionLimpia(fn () => $this->withToken($token)->postJson('/api/logout')->assertOk()
            ->assertExactJson(['message' => 'Sesión cerrada correctamente.']));
        $this->peticionLimpia(fn () => $this->withToken($token)->getJson('/api/entregas')->assertUnauthorized());
        $this->peticionLimpia(fn () => $this->withToken($otroToken)->getJson('/api/entregas')->assertOk());
        $this->peticionLimpia(fn () => $this->withoutHeader('Authorization')->postJson('/api/logout')->assertUnauthorized());
    }

    public function test_expiracion_devuelta_corresponde_a_la_configuracion(): void
    {
        $chofer = User::factory()->chofer()->create(['password' => 'password']);
        $antes = now();
        $respuesta = $this->postJson('/api/login', ['email' => $chofer->email, 'password' => 'password'])->assertOk();
        $expira = \Illuminate\Support\Carbon::parse($respuesta->json('expires_at'));
        $duracionEsperada = (int) config('sanctum.expiration') * 60;

        $this->assertGreaterThanOrEqual($antes->getTimestamp() + $duracionEsperada - 2, $expira->getTimestamp());
        $this->assertLessThanOrEqual(now()->getTimestamp() + $duracionEsperada + 2, $expira->getTimestamp());
    }

    private function login(User $usuario, string $dispositivo): string
    {
        return $this->postJson('/api/login', [
            'email' => $usuario->email,
            'password' => 'password',
            'device_name' => $dispositivo,
        ])->assertOk()->json('token');
    }

    private function peticionLimpia(callable $peticion): mixed
    {
        $this->app['auth']->forgetGuards();

        return $peticion();
    }
}
