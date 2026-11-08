<?php

namespace Tests\Feature;

use App\Models\Operador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperadorHorarioMensualTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_reads_horario_mensual_for_operadores(): void
    {
        $payload = [
            'nombre' => 'Ana',
            'apellido' => 'Gomez',
            'email' => 'ana@example.com',
            'usuario' => 'ana',
            'contraseña' => 'secret123',
            'DNI' => '12345678',
            'horario_mensual' => [
                ['dia_numero' => 1, 'turno_m' => 'AD', 'turno_t' => 'C', 'turno_n' => ''],
                ['dia_numero' => 2, 'turno_m' => '', 'turno_t' => 'V', 'turno_n' => ''],
            ],
        ];

        $operador = Operador::create($payload);

        $this->assertSame($payload['horario_mensual'], $operador->fresh()->horario_mensual);
        $this->assertDatabaseHas('operadores', ['email' => 'ana@example.com']);
    }

    
}
