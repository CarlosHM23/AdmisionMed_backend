<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionConstraintsTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_12_foreign_keys_persist_and_duplicate_patient_and_staff_identifiers_are_rejected(): void
    {
        $operatorUser = User::factory()->operador()->create();
        $specialty = $this->createSpecialtyRecord();
        $patient = $this->createPatientRecord([
            'dni' => '75000001',
            'HistoriaClinica' => 'HC-ADM-12-01',
        ]);
        $healthStaff = $this->createHealthStaffRecord($specialty, [
            'dni' => '75000002',
            'email' => 'salud.duplicado@example.com',
        ]);
        $operator = $this->createOperatorRecord();

        $appointment = $this->createAppointmentRecord(
            $patient,
            $healthStaff,
            $specialty,
            $operator
        );

        $this->assertDatabaseHas('citas', [
            'id' => $appointment->id,
            'paciente_id' => $patient->id,
            'personal_salud_id' => $healthStaff->id,
            'especialidad_id' => $specialty->id,
            'operador_id' => $operator->id,
        ]);

        $this->authenticateAs($operatorUser)
            ->postJson('/api/pacientes', [
                'nombre' => 'Duplicado',
                'apellido' => 'Paciente',
                'dni' => '75000001',
                'HistoriaClinica' => 'HC-ADM-12-01',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'HistoriaClinica']);

        $this->postJson('/api/personal_salud', [
            'nombres' => 'Duplicado',
            'apellidos' => 'Personal',
            'dni' => '75000002',
            'email' => 'salud.duplicado@example.com',
            'especialidad_id' => $specialty->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'email']);
    }

    public function test_cp_adm_12_rejects_references_to_nonexistent_foreign_rows(): void
    {
        $operatorUser = User::factory()->operador()->create();

        $this->authenticateAs($operatorUser)
            ->postJson('/api/citas', [
                'paciente_id' => 999999,
                'personal_salud_id' => 999999,
                'especialidad_id' => 999999,
                'fecha' => '2026-11-25',
                'hora' => '11:00',
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

        $this->assertDatabaseCount('citas', 0);
    }
}
