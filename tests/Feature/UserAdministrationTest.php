<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_user_administration(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
        foreach ([User::factory()->encargadoInventario()->create(), User::factory()->chofer()->create()] as $user) {
            $this->actingAs($user)->get(route('usuarios.index'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->get(route('usuarios.index'))->assertOk();
    }

    public function test_creates_verified_active_user_and_ignores_submitted_verification_timestamp(): void
    {
        $this->actingAs(User::factory()->create())->post(route('usuarios.store'), [
            'name' => 'Nuevo usuario', 'email' => 'nuevo@example.com', 'rol' => 'chofer',
            'password' => 'Fuerte-Password-2026', 'password_confirmation' => 'Fuerte-Password-2026',
            'email_verified_at' => '2000-01-01 00:00:00',
        ])->assertRedirect(route('usuarios.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com', 'rol' => 'chofer', 'activo' => true]);
        $this->assertNotNull(User::where('email', 'nuevo@example.com')->first()->email_verified_at);
        $this->assertNotSame('2000-01-01 00:00:00', User::where('email', 'nuevo@example.com')->first()->email_verified_at->toDateTimeString());
    }

    public function test_create_user_validation_and_required_role(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);
        $this->actingAs($admin);
        $validPassword = ['password' => 'Fuerte-Password-2026', 'password_confirmation' => 'Fuerte-Password-2026'];
        $this->from(route('usuarios.create'))->post(route('usuarios.store'), ['name' => 'Duplicado', 'email' => 'taken@example.com', 'rol' => 'chofer'] + $validPassword)->assertSessionHasErrors('email');
        $this->from(route('usuarios.create'))->post(route('usuarios.store'), ['name' => 'Inválido', 'email' => 'invalid@example.com', 'rol' => 'hacker'] + $validPassword)->assertSessionHasErrors('rol');
        $this->from(route('usuarios.create'))->post(route('usuarios.store'), ['name' => 'Sin rol', 'email' => 'norole@example.com'] + $validPassword)->assertSessionHasErrors('rol');
        $this->from(route('usuarios.create'))->post(route('usuarios.store'), ['name' => 'Débil', 'email' => 'weak@example.com', 'rol' => 'chofer', 'password' => '123', 'password_confirmation' => '123'])->assertSessionHasErrors('password');
        $this->from(route('usuarios.create'))->post(route('usuarios.store'), ['name' => 'Sin confirmación', 'email' => 'unconfirmed@example.com', 'rol' => 'chofer', 'password' => 'Fuerte-Password-2026'])->assertSessionHasErrors('password');
    }

    public function test_updates_user_and_accepts_own_existing_email(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->chofer()->create();
        $verifiedAt = $target->email_verified_at;
        $this->actingAs($admin)->put(route('usuarios.update', $target), ['name' => 'Actualizado', 'email' => $target->email, 'rol' => 'encargado_inventario'])->assertRedirect(route('usuarios.edit', $target));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Actualizado', 'rol' => 'encargado_inventario']);
        $this->assertEquals($verifiedAt, $target->refresh()->email_verified_at);
    }

    public function test_new_password_allows_login(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->chofer()->create();
        $this->actingAs($admin)->patch(route('usuarios.password', $target), ['password' => 'Nueva-Fuerte-Password-2026', 'password_confirmation' => 'Nueva-Fuerte-Password-2026'])->assertRedirect(route('usuarios.edit', $target));
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $target->email, 'password' => 'Nueva-Fuerte-Password-2026'])->assertRedirect(route('dashboard'));
    }

    public function test_inactive_user_with_correct_password_is_rejected_as_guest(): void
    {
        $target = User::factory()->chofer()->inactivo()->create();
        $this->post(route('login'), ['email' => $target->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
        $this->assertGuest();
    }

    public function test_inactive_user_with_incorrect_password_gets_normal_error(): void
    {
        $target = User::factory()->chofer()->inactivo()->create();
        $this->post(route('login'), ['email' => $target->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_inactive_user_with_open_session_is_logged_out_on_next_request(): void
    {
        $target = User::factory()->chofer()->create();
        $this->actingAs($target);
        $target->update(['activo' => false]);
        $this->get(route('profile.edit'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reactivated_user_can_log_in_again(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->chofer()->inactivo()->create();
        $this->actingAs($admin)->patch(route('usuarios.reactivar', $target))->assertSessionHas('success');
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $target->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_admin_cannot_self_deactivate_or_remove_own_role(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->patch(route('usuarios.desactivar', $admin))->assertSessionHas('error', 'No puedes desactivar tu propia cuenta.');
        $this->put(route('usuarios.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'rol' => 'chofer'])->assertSessionHas('error', 'No puedes quitarte tu propio rol de administrador.');

    }

    public function test_admin_can_deactivate_another_admin_when_two_are_active(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $this->actingAs($admin)->patch(route('usuarios.desactivar', $otherAdmin))->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'activo' => false]);
    }

    public function test_non_numeric_user_id_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get('/usuarios/abc/edit')->assertNotFound();
    }
}
