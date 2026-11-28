<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionPatientsTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_03_patient_crud_persists_and_returns_json(): void
    {
        $operator = User::factory()->operador()->create();
        $this->authenticateAs($operator);

        $this->postJson('/api/pacientes', [
            'nombre' => 'Lucía',
            'apellido' => 'Ramírez',
            'dni' => '71000001',
            'HistoriaClinica' => 'HC-ADM-03-01',
            'email' => 'lucia@example.com',
            'direccion' => 'Calle de prueba 123',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'nombre', 'apellido', 'dni', 'HistoriaClinica']])
            ->assertJsonPath('data.nombre', 'Lucía');

        $patientId = $this->getJson('/api/pacientes?search=HC-ADM-03-01')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total'])
            ->assertJsonPath('data.0.HistoriaClinica', 'HC-ADM-03-01')
            ->json('data.0.id');

        $this->getJson('/api/pacientes/' . $patientId)
            ->assertOk()
            ->assertJsonPath('data.email', 'lucia@example.com');

        $this->putJson('/api/pacientes/' . $patientId, [
            'nombre' => 'Lucía Actualizada',
            'HistoriaClinica' => 'HC-ADM-03-01',
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'Lucía Actualizada');

        $this->assertDatabaseHas('pacientes', [
            'id' => $patientId,
            'nombre' => 'Lucía Actualizada',
        ]);

        $this->deleteJson('/api/pacientes/' . $patientId)->assertNoContent();
        $this->assertDatabaseMissing('pacientes', ['id' => $patientId]);
    }

    public function test_cp_adm_03_patient_creation_validates_required_and_malformed_fields(): void
    {
        $operator = User::factory()->operador()->create();
        $this->authenticateAs($operator)
            ->postJson('/api/pacientes', [
                'nombre' => 'Paciente',
                'apellido' => 'Inválido',
                'dni' => '71000004',
                'HistoriaClinica' => 'HC-ADM-03-INVALID',
                'email' => 'correo-invalido',
                'direccion' => 'Calle de prueba',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/pacientes', [
            'nombre' => 'Paciente',
            'apellido' => 'Sin historia',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'HistoriaClinica', 'direccion']);
    }

    public function test_cp_adm_03_patient_search_matches_name_surname_dni_and_clinical_history(): void
    {
        $operator = User::factory()->operador()->create();
        $this->authenticateAs($operator);

        $this->postJson('/api/pacientes', [
            'nombre' => 'Andrea',
            'apellido' => 'Salas',
            'dni' => '71000002',
            'HistoriaClinica' => 'HC-SEARCH-02',
            'direccion' => 'Calle de prueba 2',
        ])->assertCreated();

        $this->postJson('/api/pacientes', [
            'nombre' => 'Bruno',
            'apellido' => 'Paz',
            'dni' => '71000003',
            'HistoriaClinica' => 'HC-SEARCH-03',
            'direccion' => 'Calle de prueba 3',
        ])->assertCreated();

        foreach (['Andrea', 'Salas', '71000002', 'HC-SEARCH-02'] as $term) {
            $this->getJson('/api/pacientes?search=' . urlencode($term))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.nombre', 'Andrea');
        }
    }
}
