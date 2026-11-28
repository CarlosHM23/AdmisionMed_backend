<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionIntegratedWorkflowTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_14_supervisor_admission_and_health_staff_complete_appointment_flow(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->authenticateAs($supervisor);

        $specialty = $this->postJson('/api/especialidades', [
            'UPS' => 'UPS-ADM-14',
            'especialidad' => 'Medicina General',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'UPS', 'especialidad']])
            ->json('data');

        $operator = $this->postJson('/api/operadores', [
            'nombre' => 'Carlos',
            'apellido' => 'Ingreso',
            'DNI' => '76000001',
            'email' => 'carlos.ingreso@example.com',
            'usuario' => 'carlos.ingreso',
            'password' => 'clave1234',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'user' => ['id', 'email']]])
            ->json('data');

        $operatorUser = User::findOrFail($operator['user']['id']);
        $this->withToken($this->apiTokenFor($operatorUser, 'clave1234'));

        $patient = $this->postJson('/api/pacientes', [
            'nombre' => 'Sofía',
            'apellido' => 'Consulta',
            'dni' => '76000002',
            'HistoriaClinica' => 'HC-ADM-14-01',
            'direccion' => 'Avenida Central 123',
        ])->assertCreated()
            ->assertJsonPath('data.HistoriaClinica', 'HC-ADM-14-01')
            ->json('data');

        $healthStaff = $this->postJson('/api/personal_salud', [
            'nombres' => 'Elena',
            'apellidos' => 'Médica',
            'dni' => '76000003',
            'email' => 'elena.medica@example.com',
            'password' => 'clave1234',
            'especialidad_id' => $specialty['id'],
        ])->assertCreated()
            ->assertJsonPath('data.especialidad_id', $specialty['id'])
            ->json('data');

        $appointment = $this->postJson('/api/citas', [
            'paciente_id' => $patient['id'],
            'personal_salud_id' => $healthStaff['id'],
            'especialidad_id' => $specialty['id'],
            'fecha' => '2026-11-26',
            'hora' => '09:30',
            'estado' => 'pendiente',
            'nro_ticket' => 1,
            'operador_id' => $operator['id'],
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'paciente_id', 'especialidad_id', 'nro_ticket']])
            ->json('data');

        $this->getJson('/api/citas/' . $appointment['id'])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'paciente' => ['id', 'nombre', 'dni'],
                    'personal_salud' => ['id', 'nombres', 'especialidad_id'],
                    'especialidad' => ['id', 'UPS', 'especialidad'],
                    'operador' => ['id', 'usuario', 'DNI'],
                ],
            ])
            ->assertJsonPath('data.paciente.id', $patient['id'])
            ->assertJsonPath('data.personal_salud.id', $healthStaff['id'])
            ->assertJsonPath('data.especialidad.id', $specialty['id'])
            ->assertJsonPath('data.operador.id', $operator['id']);
    }
}
