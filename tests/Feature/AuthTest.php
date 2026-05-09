<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ==================== TESTS DE REGISTRO ====================

    public function test_usuario_puede_registrarse(): void
    {
        $data = [
            'name' => 'Juan García',
            'email' => 'juan@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com']);
    }

    public function test_registro_requiere_email_unico(): void
    {
        User::factory()->create(['email' => 'juan@example.com']);

        $data = [
            'name' => 'Juan García',
            'email' => 'juan@example.com',  // Email ya existe
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registro_requiere_contraseña_fuerte(): void
    {
        $data = [
            'name' => 'Juan García',
            'email' => 'juan@example.com',
            'password' => '123',  // Contraseña muy débil
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/api/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registro_requiere_campos_obligatorios(): void
    {
        $data = [
            'email' => 'juan@example.com',
            // Sin nombre ni password
        ];

        $response = $this->postJson('/api/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'password']);
    }

    // ==================== TESTS DE LOGIN ====================

    public function test_usuario_puede_hacer_login(): void
    {
        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);
    }

    public function test_login_con_credenciales_invalidas(): void
    {
        User::factory()->create([
            'email' => 'juan@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'PasswordIncorrecto!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Credenciales inválidas']);
    }

    public function test_login_con_email_no_existe(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'noexiste@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_requiere_email_y_password(): void
    {
        $response = $this->postJson('/api/login', [
            // Sin email ni password
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ==================== TESTS DE LOGOUT ====================

    public function test_usuario_autenticado_puede_deslogearse(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada exitosamente']);
    }

    public function test_logout_sin_autenticacion_falla(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    // ==================== TESTS DE PROTECCIÓN ====================

    public function test_rutas_protegidas_requieren_autenticacion(): void
    {
        $response = $this->getJson('/api/reservas');

        $response->assertStatus(401);
    }

    public function test_usuario_autenticado_accede_rutas_protegidas(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/reservas');

        // No debe ser 401 (Unauthorized)
        $this->assertNotEquals(401, $response->status());
    }

    // ==================== TESTS DE TOKEN ====================

    public function test_token_devuelto_es_valido(): void
    {
        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'juan@example.com',
            'password' => 'Password123!',
        ]);

        $token = $response->json('data.token');

        // Usar el token para acceder a ruta protegida
        $protected = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/user');

        $protected->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_token_invalido_rechazado(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer invalid_token_12345'])
            ->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_token_expirado_rechazado(): void
    {
        // Este test depende de la configuración JWT
        // Si usa Laravel Passport o Sanctum, ajustar según corresponda
        $oldToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE2MjAwMDAwMDB9.invalid';

        $response = $this->withHeaders(['Authorization' => "Bearer $oldToken"])
            ->getJson('/api/user');

        $response->assertStatus(401);
    }

    // ==================== TESTS DE PERFIL ====================

    public function test_usuario_autenticado_puede_ver_su_perfil(): void
    {
        $user = User::factory()->create(['name' => 'Juan García']);

        $response = $this->actingAs($user)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Juan García')
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_usuario_autenticado_puede_actualizar_perfil(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/user', [
                'name' => 'Nuevo Nombre',
                'email' => 'nuevo@example.com',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nuevo Nombre',
            'email' => 'nuevo@example.com',
        ]);
    }

    public function test_actualizar_perfil_sin_autenticacion_falla(): void
    {
        $response = $this->putJson('/api/user', [
            'name' => 'Nuevo Nombre',
        ]);

        $response->assertStatus(401);
    }
}
