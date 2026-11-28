<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionHealthStaffTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_06_health_staff_crud_keeps_specialty_relationship(): void
    {
        $operator = User::factory()->operador()->create();
        $specialty = $this->createSpecialtyRecord(['UPS' => 'UPS-ADM-06', 'especialidad' => 'Pediatría']);
        $otherSpecialty = $this->createSpecialtyRecord(['UPS' => 'UPS-ADM-06-2', 'especialidad' => 'Medicina']);

        $this->authenticateAs($operator);

        $created = $this->postJson('/api/personal_salud', [
            'nombres' => 'María',
            'apellidos' => 'Vega',
            'dni' => '73000001',
            'telefono' => '987654321',
            'email' => 'maria.vega@example.com',
            'password' => 'clave1234',
            'especialidad_id' => $specialty->id,
            'cargo' => 'Pediatra',
        ])->assertCreated()
            ->assertJsonPath('data.especialidad_id', $specialty->id);

        $staffId = $created->json('data.id');

        $this->getJson('/api/personal_salud/' . $staffId)
            ->assertOk()
            ->assertJsonPath('data.especialidad.id', $specialty->id)
            ->assertJsonPath('data.especialidad.especialidad', 'Pediatría');

        $this->putJson('/api/personal_salud/' . $staffId, [
            'especialidad_id' => $otherSpecialty->id,
            'cargo' => 'Médica general',
        ])->assertOk()
            ->assertJsonPath('data.especialidad_id', $otherSpecialty->id);

        $this->assertDatabaseHas('personal_salud', [
            'id' => $staffId,
            'especialidad_id' => $otherSpecialty->id,
        ]);

        $this->deleteJson('/api/personal_salud/' . $staffId)->assertOk();
        $this->assertDatabaseMissing('personal_salud', ['id' => $staffId]);
    }

    public function test_cp_adm_06_health_staff_rejects_nonexistent_specialty_reference(): void
    {
        $operator = User::factory()->operador()->create();

        $this->authenticateAs($operator)
            ->postJson('/api/personal_salud', [
                'nombres' => 'Personal',
                'apellidos' => 'Sin especialidad',
                'dni' => '73000002',
                'email' => 'sin.especialidad@example.com',
                'especialidad_id' => 999999,
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['especialidad_id']);

        $this->assertDatabaseMissing('personal_salud', ['dni' => '73000002']);
    }
}
