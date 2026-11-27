<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstallationSmokeTest extends TestCase
{
    use RefreshDatabase;

    // Ejecutar con `php artisan test --filter=InstallationSmokeTest`; verifica migraciones limpias y seeders.
    public function test_migrations_and_seeders_create_the_expected_installation_state(): void
    {
        $this->seed();

        foreach (['users', 'especialidades', 'pacientes', 'operadores', 'personal_salud', 'citas', 'cupos_por_especialidad', 'roles'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing migrated table: {$table}");
        }

        $this->assertDatabaseHas('users', ['email' => 'supervisor@centrosalud.pe']);
        $this->assertTrue(Role::where('name', 'supervisor')->where('guard_name', 'api')->exists());
    }
}
