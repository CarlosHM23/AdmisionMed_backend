<?php

namespace Tests\Feature;

use App\Models\User;

class AdmissionAppointmentFiltersTest extends AdmissionFeatureTestCase
{
    public function test_cp_adm_09_each_appointment_filter_works_independently(): void
    {
        $operatorUser = User::factory()->operador()->create();
        [$target, $other, $targetPatient, $otherSpecialty, $otherStaff] = $this->createTwoAppointments();
        $this->authenticateAs($operatorUser);

        $this->assertOnlyAppointment('/api/citas?search=' . urlencode('Ana'), $target->id);
        $this->assertOnlyAppointment('/api/citas?estado=completada', $other->id);
        $this->assertOnlyAppointment('/api/citas?especialidad_id=' . $target->especialidad_id, $target->id);
        $this->assertOnlyAppointment('/api/citas?personal_salud_id=' . $otherStaff->id, $other->id);
        $this->assertOnlyAppointment('/api/citas?fecha=2026-11-21', $other->id);

        $this->assertNotSame($targetPatient->id, $other->paciente_id);
        $this->assertNotSame($otherSpecialty->id, $target->especialidad_id);
    }

    public function test_cp_adm_09_appointment_filters_combine_as_an_intersection(): void
    {
        $operatorUser = User::factory()->operador()->create();
        [$target] = $this->createTwoAppointments();
        $this->authenticateAs($operatorUser);

        $url = '/api/citas?' . http_build_query([
            'search' => 'Ana',
            'estado' => 'pendiente',
            'especialidad_id' => $target->especialidad_id,
            'personal_salud_id' => $target->personal_salud_id,
            'fecha' => '2026-11-20',
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data' => [['id', 'paciente_id', 'especialidad_id', 'personal_salud_id', 'estado', 'fecha']],
                'total',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    private function assertOnlyAppointment(string $url, int $appointmentId): void
    {
        $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appointmentId);
    }

    private function createTwoAppointments(): array
    {
        $targetSpecialty = $this->createSpecialtyRecord(['especialidad' => 'Cardiología']);
        $otherSpecialty = $this->createSpecialtyRecord(['especialidad' => 'Dermatología']);
        $targetStaff = $this->createHealthStaffRecord($targetSpecialty);
        $otherStaff = $this->createHealthStaffRecord($otherSpecialty);
        $operator = $this->createOperatorRecord();
        $targetPatient = $this->createPatientRecord([
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
            'dni' => '74000001',
            'HistoriaClinica' => 'HC-ADM-09-A',
        ]);
        $otherPatient = $this->createPatientRecord([
            'nombre' => 'Luis',
            'apellido' => 'Prueba',
            'dni' => '74000002',
            'HistoriaClinica' => 'HC-ADM-09-B',
        ]);

        $target = $this->createAppointmentRecord(
            $targetPatient,
            $targetStaff,
            $targetSpecialty,
            $operator,
            ['fecha' => '2026-11-20', 'estado' => 'pendiente', 'nro_ticket' => '901']
        );
        $other = $this->createAppointmentRecord(
            $otherPatient,
            $otherStaff,
            $otherSpecialty,
            $operator,
            ['fecha' => '2026-11-21', 'estado' => 'completada', 'nro_ticket' => '902']
        );

        return [$target, $other, $targetPatient, $otherSpecialty, $otherStaff];
    }
}
