<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoriaClinicaConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');

        Role::firstOrCreate(['name' => 'operador', 'guard_name' => 'api']);
        $user = User::create([
            'name' => 'Operador de prueba',
            'email' => 'usuario@example.test',
            'password' => 'synthetic-password',
        ]);
        $user->assignRole('operador');

        config(['jwt.secret' => 'synthetic-test-secret']);
        $this->withToken(auth('api')->login($user));
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh');

        parent::tearDown();
    }

    public function test_history_clinic_numbers_advance_for_successive_patient_registrations(): void
    {
        foreach (['12345678', '23456789'] as $index => $dni) {
            $nextNumber = $this->getJson('/api/pacientes/next-hc')
                ->assertOk()
                ->json('data.next_hc');

            $response = $this->postJson('/api/pacientes', [
                'nombre' => 'Paciente',
                'apellido' => 'Prueba',
                'dni' => $dni,
                'HistoriaClinica' => $nextNumber,
                'direccion' => 'Direccion de prueba',
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.HistoriaClinica', 'H-'.($index + 1));
        }

        $this->assertSame(2, DB::table('pacientes')->count());
    }

    public function test_database_rejects_duplicate_history_clinic_numbers(): void
    {
        DB::table('pacientes')->insert($this->patientData('12345678', 'H-1'));

        $this->expectException(QueryException::class);
        DB::table('pacientes')->insert($this->patientData('23456789', 'H-1'));
    }

    public function test_parallel_patient_registrations_cannot_duplicate_a_history_clinic_number(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Parallel patient registration test requires PostgreSQL and pcntl.');
        }

        $historyClinicNumber = $this->getJson('/api/pacientes/next-hc')
            ->assertOk()
            ->json('data.next_hc');
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertNotFalse($sockets);
        [$parentSocket, $childSocket] = $sockets;
        stream_set_timeout($parentSocket, 10);
        DB::disconnect();

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'Could not start the parallel request process.');

        if ($pid === 0) {
            fclose($parentSocket);
            fread($childSocket, 1);
            DB::purge();

            try {
                $response = $this->postJson('/api/pacientes', [
                    'nombre' => 'Paciente',
                    'apellido' => 'Paralelo',
                    'dni' => '12345678',
                    'HistoriaClinica' => $historyClinicNumber,
                    'direccion' => 'Direccion de prueba',
                ]);
                fwrite($childSocket, json_encode(['status' => $response->status()]));
            } catch (\Throwable) {
                fwrite($childSocket, json_encode(['status' => 500]));
            }

            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        fwrite($parentSocket, '1');
        fflush($parentSocket);
        DB::purge();
        $parentResponse = $this->postJson('/api/pacientes', [
            'nombre' => 'Paciente',
            'apellido' => 'Paralelo',
            'dni' => '23456789',
            'HistoriaClinica' => $historyClinicNumber,
            'direccion' => 'Direccion de prueba',
        ]);
        $childResult = json_decode(stream_get_contents($parentSocket), true);
        fclose($parentSocket);
        pcntl_waitpid($pid, $status);

        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertIsArray($childResult);
        $statuses = [$parentResponse->status(), $childResult['status']];
        $this->assertSame(1, count(array_filter($statuses, fn ($responseStatus) => $responseStatus === 201)));
        $this->assertSame(1, DB::table('pacientes')->where('HistoriaClinica', $historyClinicNumber)->count());
    }

    private function patientData(string $dni, string $historyClinicNumber): array
    {
        return [
            'nombre' => 'Paciente',
            'apellido' => 'Prueba',
            'dni' => $dni,
            'HistoriaClinica' => $historyClinicNumber,
            'direccion' => 'Direccion de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
