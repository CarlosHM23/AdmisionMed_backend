<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionAppointmentsTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_07_appointment_crud_persists_foreign_keys_and_json_response(): void
    {
        $operatorUser = User::factory()->operador()->create();
        $patient = $this->createPatientRecord();
        $specialty = $this->createSpecialtyRecord();
        $healthStaff = $this->createHealthStaffRecord($specialty);
        $operator = $this->createOperatorRecord();

        $this->authenticateAs($operatorUser);

        $created = $this->postJson('/api/citas', [
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-11-22',
            'hora' => '10:30',
            'estado' => 'pendiente',
            'nro_ticket' => 999,
            'operador_id' => $operator->id,
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'paciente_id', 'especialidad_id', 'nro_ticket']]);

        $appointmentId = $created->json('data.id');

        $this->assertDatabaseHas('citas', [
            'id' => $appointmentId,
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'operador_id' => $operator->id,
        ]);

        $this->getJson('/api/citas/' . $appointmentId)
            ->assertOk()
            ->assertJsonPath('data.paciente.id', $patient->id)
            ->assertJsonPath('data.personal_salud.id', $healthStaff->id)
            ->assertJsonPath('data.especialidad.id', $specialty->id)
            ->assertJsonPath('data.operador.id', $operator->id);

        $this->putJson('/api/citas/' . $appointmentId, [
            'estado' => 'completada',
            'observaciones' => 'Atención finalizada',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'completada');

        $this->assertDatabaseHas('citas', ['id' => $appointmentId, 'estado' => 'completada']);

        $this->deleteJson('/api/citas/' . $appointmentId)
            ->assertOk()
            ->assertJsonPath('data.id', (string) $appointmentId);
        $this->assertDatabaseMissing('citas', ['id' => $appointmentId]);
    }

    public function test_cp_adm_07_appointment_rejects_invalid_foreign_keys_and_state(): void
    {
        $operatorUser = User::factory()->operador()->create();
        $this->authenticateAs($operatorUser);

        $this->postJson('/api/citas', [
            'paciente_id' => 999999,
            'personal_salud_id' => 999999,
            'especialidad_id' => 999999,
            'fecha' => '2026-11-22',
            'hora' => '10:30',
            'estado' => 'pendiente',
            'nro_ticket' => 1,
            'operador_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'paciente_id',
                'personal_salud_id',
                'especialidad_id',
                'operador_id',
            ]);

        $patient = $this->createPatientRecord();
        $specialty = $this->createSpecialtyRecord();
        $healthStaff = $this->createHealthStaffRecord($specialty);
        $operator = $this->createOperatorRecord();

        $this->postJson('/api/citas', [
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-11-22',
            'hora' => '10:30',
            'estado' => 'no-valido',
            'nro_ticket' => 1,
            'operador_id' => $operator->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->postJson('/api/citas', [
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-11-22',
            'hora' => '10:30',
            'estado' => null,
            'nro_ticket' => 1,
            'operador_id' => $operator->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->assertDatabaseCount('citas', 0);
    }

    public function test_cp_adm_07_update_rejects_state_outside_database_constraint(): void
    {
        $operatorUser = User::factory()->operador()->create();
        $patient = $this->createPatientRecord();
        $specialty = $this->createSpecialtyRecord();
        $healthStaff = $this->createHealthStaffRecord($specialty);
        $operator = $this->createOperatorRecord();
        $appointment = $this->createAppointmentRecord(
            $patient,
            $healthStaff,
            $specialty,
            $operator
        );

        $this->authenticateAs($operatorUser)
            ->putJson('/api/citas/' . $appointment->id, ['estado' => 'no-valido'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->putJson('/api/citas/' . $appointment->id, ['estado' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->assertDatabaseHas('citas', [
            'id' => $appointment->id,
            'estado' => 'pendiente',
        ]);
    }
}
