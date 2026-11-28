<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionAuthProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_cp_adm_01_valid_login_returns_jwt_and_operator_can_access_protected_route(): void
    {
        $user = User::factory()->operador()->create([
            'email' => 'operador@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'bearer');

        $this->withToken($response->json('access_token'))
            ->getJson('/api/pacientes')
            ->assertOk();
    }

    public function test_cp_adm_01_invalid_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'operador@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'operador@example.com',
            'password' => 'incorrecta',
        ])->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_cp_adm_01_login_validates_required_credentials(): void
    {
        $this->postJson('/api/login', [
            'email' => 'no-es-correo',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cp_adm_01_authenticated_request_for_missing_patient_returns_404(): void
    {
        $user = User::factory()->operador()->create();

        $this->withToken($this->loginToken($user))
            ->getJson('/api/pacientes/999999')
            ->assertNotFound();
    }

    public function test_cp_adm_02_profile_can_be_read_and_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre anterior',
            'apellido' => 'Apellido',
            'email' => 'perfil@example.com',
            'telefono' => '999111222',
        ]);

        $this->withToken($this->loginToken($user))
            ->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre anterior')
            ->assertJsonPath('data.email', 'perfil@example.com');

        $this->putJson('/api/user/profile', [
            'name' => 'Nombre actualizado',
            'apellido' => 'Nuevo apellido',
            'email' => 'perfil@example.com',
            'telefono' => '999333444',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nombre actualizado')
            ->assertJsonPath('data.telefono', '999333444');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre actualizado',
            'telefono' => '999333444',
        ]);
    }

    public function test_cp_adm_02_profile_update_rejects_invalid_and_duplicate_email_and_missing_name(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'ocupado@example.com']);

        $this->withToken($this->loginToken($user))
            ->putJson('/api/user/profile', [
                'name' => 'Usuario',
                'email' => 'correo-invalido',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->putJson('/api/user/profile', [
            'name' => 'Usuario',
            'email' => 'ocupado@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->putJson('/api/user/profile', [
            'email' => $user->email,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cp_adm_02_password_can_be_changed_and_used_to_login(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->withToken($this->loginToken($user, 'password123'))
            ->postJson('/api/user/change-password', [
                'new_password' => 'nueva-clave-123',
            ])->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada correctamente');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'nueva-clave-123',
        ])->assertOk()
            ->assertJsonStructure(['access_token']);
    }

    public function test_cp_adm_02_password_change_validates_required_minimum_and_different_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->withToken($this->loginToken($user, 'password123'))
            ->postJson('/api/user/change-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);

        $this->postJson('/api/user/change-password', [
            'new_password' => 'corta',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);

        $this->postJson('/api/user/change-password', [
            'current_password' => 'password123',
            'new_password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_cp_adm_11_missing_or_invalid_token_returns_401(): void
    {
        $this->getJson('/api/user/profile')->assertUnauthorized();

        $this->withToken('token-invalido')
            ->getJson('/api/user/profile')
            ->assertUnauthorized();
    }

    public function test_cp_adm_11_user_without_role_is_forbidden_and_supervisor_is_allowed(): void
    {
        $unassignedUser = User::factory()->create();

        $this->withToken($this->loginToken($unassignedUser))
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();

        $supervisor = User::factory()->supervisor()->create();

        $this->withToken($this->loginToken($supervisor))
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('ok', 'admin');
    }

    private function loginToken(User $user, string $password = 'password'): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();

        return $response->json('access_token');
    }
}
