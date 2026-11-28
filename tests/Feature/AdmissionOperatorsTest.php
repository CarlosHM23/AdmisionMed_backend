<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionOperatorsTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_05_supervisor_can_complete_operator_crud(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->authenticateAs($supervisor);

        $payload = [
            'nombre' => 'Eva',
            'apellido' => 'Soto',
            'DNI' => '72000001',
            'email' => 'eva.soto@example.com',
            'usuario' => 'eva.soto',
            'password' => 'clave1234',
        ];

        $created = $this->postJson('/api/operadores', $payload)
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'user' => ['id', 'email']]]);

        $operatorId = $created->json('data.id');
        $userId = $created->json('data.user.id');

        $this->assertDatabaseHas('operadores', [
            'id' => $operatorId,
            'DNI' => '72000001',
            'email' => 'eva.soto@example.com',
        ]);
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'eva.soto@example.com']);

        $this->getJson('/api/operadores')
            ->assertOk()
            ->assertJsonPath('data.0.id', $operatorId);

        $this->getJson('/api/operadores/' . $operatorId)
            ->assertOk()
            ->assertJsonPath('data.user.id', $userId);

        $this->putJson('/api/operadores/' . $operatorId, [
            'nombre' => 'Eva María',
            'apellido' => 'Soto',
            'DNI' => '72000001',
            'email' => 'eva.soto@example.com',
            'usuario' => 'eva.soto',
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'Eva María')
            ->assertJsonPath('data.user.name', 'Eva María Soto');

        $this->assertDatabaseHas('operadores', ['id' => $operatorId, 'nombre' => 'Eva María']);

        $this->deleteJson('/api/operadores/' . $operatorId)->assertOk();
        $this->assertDatabaseMissing('operadores', ['id' => $operatorId]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_cp_adm_05_operator_creation_rejects_duplicate_identity_fields(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->authenticateAs($supervisor);

        $this->postJson('/api/operadores', [
            'nombre' => 'Eva',
            'apellido' => 'Soto',
            'DNI' => '72000002',
            'email' => 'eva.soto@example.com',
            'usuario' => 'eva.soto',
            'password' => 'clave1234',
        ])->assertCreated();

        $this->postJson('/api/operadores', [
            'nombre' => 'Otra',
            'apellido' => 'Persona',
            'DNI' => '72000002',
            'email' => 'eva.soto@example.com',
            'usuario' => 'eva.soto',
            'password' => 'clave1234',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['DNI', 'email', 'usuario']);
    }

    public function test_cp_adm_05_non_supervisor_cannot_access_operator_crud(): void
    {
        $operator = User::factory()->operador()->create();

        $this->authenticateAs($operator)
            ->getJson('/api/operadores')
            ->assertForbidden();

        $this->postJson('/api/operadores', [])->assertForbidden();
    }
}
