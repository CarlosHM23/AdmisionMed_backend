<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Operador;
use App\Models\Paciente;
use App\Models\PersonalSalud;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdmissionFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    private static int $fixtureSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function apiTokenFor(User $user, string $password = 'password'): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()->assertJsonStructure(['access_token']);

        return $response->json('access_token');
    }

    protected function authenticateAs(User $user): static
    {
        return $this->withToken($this->apiTokenFor($user));
    }

    protected function createPatientRecord(array $attributes = []): Paciente
    {
        $sequence = $this->nextFixtureSequence();

        return Paciente::create(array_replace([
            'nombre' => 'Paciente ' . $sequence,
            'apellido' => 'Prueba',
            'genero' => 'Femenino',
            'fecha_nacimiento' => '1990-01-01',
            'tipo_documento' => 'DNI',
            'dni' => sprintf('%08d', $sequence),
            'HistoriaClinica' => 'HC-' . $sequence,
            'telefono' => '999000000',
            'email' => 'paciente' . $sequence . '@example.com',
            'direccion' => 'Dirección de prueba',
            'gestante' => false,
            'etapa_vida' => 'Adulto',
            'detalle_gestante' => null,
            'estado' => 'Activo',
        ], $attributes));
    }

    protected function createSpecialtyRecord(array $attributes = []): Especialidad
    {
        $sequence = $this->nextFixtureSequence();

        return Especialidad::create(array_replace([
            'UPS' => 'UPS-' . $sequence,
            'especialidad' => 'Especialidad ' . $sequence,
        ], $attributes));
    }

    protected function createOperatorRecord(array $attributes = []): Operador
    {
        $sequence = $this->nextFixtureSequence();

        return Operador::create(array_replace([
            'nombre' => 'Operador ' . $sequence,
            'apellido' => 'Prueba',
            'DNI' => sprintf('%08d', $sequence),
            'email' => 'operador' . $sequence . '@example.com',
            'usuario' => 'operador' . $sequence,
            'contraseña' => 'clave-segura',
            'horario_mensual' => null,
        ], $attributes));
    }

    protected function createHealthStaffRecord(Especialidad $specialty, array $attributes = []): PersonalSalud
    {
        $sequence = $this->nextFixtureSequence();

        return PersonalSalud::create(array_replace([
            'nombres' => 'Personal ' . $sequence,
            'apellidos' => 'Prueba',
            'dni' => sprintf('%08d', $sequence),
            'telefono' => '988000000',
            'email' => 'salud' . $sequence . '@example.com',
            'especialidad_id' => $specialty->id,
            'cargo' => 'Médico',
        ], $attributes));
    }

    protected function createAppointmentRecord(
        Paciente $patient,
        PersonalSalud $healthStaff,
        Especialidad $specialty,
        Operador $operator,
        array $attributes = []
    ): Cita {
        $sequence = $this->nextFixtureSequence();

        return Cita::create(array_replace([
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-11-20',
            'turno' => 'mañana',
            'hora' => '09:00',
            'operador_id' => $operator->id,
            'observaciones' => null,
            'estado' => 'pendiente',
            'nro_ticket' => (string) $sequence,
            'total_tickets_dia' => 16,
        ], $attributes));
    }

    protected function nextFixtureSequence(): int
    {
        return ++self::$fixtureSequence;
    }
}
