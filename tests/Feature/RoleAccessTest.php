<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const WEB_ROUTES = [
        '/dashboard',
        '/clientes',
        '/pedidos',
        '/entregas',
        '/inventario',
        '/inventario/movimientos',
        '/profile',
    ];

    public function test_admin_can_access_all_web_sections(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::WEB_ROUTES as $uri) {
            $this->get($uri)->assertOk();
        }
        $this->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_inventory_manager_can_access_only_inventory_dashboard_and_profile(): void
    {
        $this->actingAs(User::factory()->encargadoInventario()->create());

        foreach (['/dashboard', '/inventario', '/inventario/movimientos', '/profile'] as $uri) {
            $this->get($uri)->assertOk();
        }

        foreach (['/clientes', '/pedidos', '/entregas'] as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_chofer_and_user_without_role_are_denied_every_web_section(): void
    {
        foreach ([User::factory()->chofer()->create(), User::factory()->sinRol()->create()] as $user) {
            $this->actingAs($user);
            foreach (self::WEB_ROUTES as $uri) {
                $this->get($uri)->assertForbidden()
                    ->assertSee('Tu cuenta no tiene acceso a la versión web')
                    ->assertSee('Cerrar sesión');
            }
            $this->get('/')->assertForbidden()->assertSee('Tu cuenta no tiene acceso a la versión web');
        }
    }

    public function test_guests_are_redirected_to_login_for_protected_sections(): void
    {
        foreach (self::WEB_ROUTES as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
        $this->get('/')->assertRedirect(route('login'));
    }
}
