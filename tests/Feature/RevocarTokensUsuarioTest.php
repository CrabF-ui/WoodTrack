<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevocarTokensUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_desactivar_chofer_borra_todos_sus_tokens_y_el_token_anterior_responde_no_autorizado(): void
    {
        [$admin, $chofer, $otroUsuario] = $this->crearUsuarios();
        $primerToken = $chofer->createToken('telefono')->plainTextToken;
        $chofer->createToken('tablet');
        $tokenOtroUsuario = $otroUsuario->createToken('telefono')->accessToken;

        $this->actingAs($admin)
            ->patch(route('usuarios.desactivar', $chofer))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $chofer->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenOtroUsuario->id]);

        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->withToken($primerToken)->getJson('/api/entregas')->assertUnauthorized();
    }

    public function test_cambiar_rol_de_chofer_borra_sus_tokens_y_conserva_los_de_otro_usuario(): void
    {
        [$admin, $chofer, $otroUsuario] = $this->crearUsuarios();
        $chofer->createToken('telefono');
        $tokenOtroUsuario = $otroUsuario->createToken('telefono')->accessToken;

        $this->actingAs($admin)->put(route('usuarios.update', $chofer), [
            'name' => $chofer->name,
            'email' => $chofer->email,
            'rol' => User::ROL_ENCARGADO_INVENTARIO,
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $chofer->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenOtroUsuario->id]);
    }

    public function test_restablecer_contrasena_de_chofer_borra_sus_tokens_y_conserva_los_de_otro_usuario(): void
    {
        [$admin, $chofer, $otroUsuario] = $this->crearUsuarios();
        $chofer->createToken('telefono');
        $tokenOtroUsuario = $otroUsuario->createToken('telefono')->accessToken;

        $this->actingAs($admin)->patch(route('usuarios.password', $chofer), [
            'password' => 'Nueva-Fuerte-Password-2026',
            'password_confirmation' => 'Nueva-Fuerte-Password-2026',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $chofer->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenOtroUsuario->id]);
    }

    public function test_editar_solo_nombre_y_correo_no_borra_tokens(): void
    {
        [$admin, $chofer, $otroUsuario] = $this->crearUsuarios();
        $tokenChofer = $chofer->createToken('telefono')->accessToken;
        $tokenOtroUsuario = $otroUsuario->createToken('telefono')->accessToken;

        $this->actingAs($admin)->put(route('usuarios.update', $chofer), [
            'name' => 'Chofer actualizado',
            'email' => 'chofer.actualizado@example.com',
            'rol' => User::ROL_CHOFER,
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenChofer->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenOtroUsuario->id]);
    }

    public function test_reactivar_usuario_no_borra_tokens(): void
    {
        $admin = User::factory()->create();
        $chofer = User::factory()->chofer()->inactivo()->create();
        $otroUsuario = User::factory()->chofer()->create();
        $tokenChofer = $chofer->createToken('telefono')->accessToken;
        $tokenOtroUsuario = $otroUsuario->createToken('telefono')->accessToken;

        $this->actingAs($admin)
            ->patch(route('usuarios.reactivar', $chofer))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenChofer->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenOtroUsuario->id]);
    }

    /**
     * @return array{User, User, User}
     */
    private function crearUsuarios(): array
    {
        return [
            User::factory()->create(),
            User::factory()->chofer()->create(),
            User::factory()->chofer()->create(),
        ];
    }
}
