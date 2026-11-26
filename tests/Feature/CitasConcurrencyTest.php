<?php

namespace Tests\Feature;

use App\Models\CuposPorEspecialidad;
use App\Models\Especialidad;
use App\Models\Operador;
use App\Models\Paciente;
use App\Models\PersonalSalud;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CitasConcurrencyTest extends TestCase
{
    // Ejecutar con `php artisan test --filter=CitasConcurrencyTest` solo en una DB de pruebas; valida cupos/tickets y concurrencia con PostgreSQL+pcntl.
    private Especialidad $especialidad;

    private Operador $operador;

    private Paciente $paciente;

    private PersonalSalud $personalSalud;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');

        $this->especialidad = Especialidad::create([
            'UPS' => 'MED',
            'especialidad' => 'Medicina general',
        ]);
        $this->operador = Operador::create([
            'nombre' => 'Operador',
            'apellido' => 'Prueba',
            'email' => 'operador@example.test',
            'usuario' => 'operador-prueba',
            'contraseña' => 'synthetic-password',
            'DNI' => '12345678',
        ]);
        $this->paciente = Paciente::create([
            'nombre' => 'Paciente',
            'apellido' => 'Sintetico',
            'dni' => '87654321',
            'HistoriaClinica' => 'HC-CONC-1',
            'direccion' => 'Direccion de prueba',
        ]);
        $this->personalSalud = PersonalSalud::create([
            'nombres' => 'Profesional',
            'apellidos' => 'Sintetico',
            'dni' => '11223344',
            'email' => 'profesional@example.test',
            'especialidad_id' => $this->especialidad->id,
        ]);

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

    public function test_ticket_numbers_are_unique_per_date_and_specialty_and_stop_at_capacity(): void
    {
        $fecha = now()->addDay()->toDateString();
        CuposPorEspecialidad::create([
            'fecha' => $fecha,
            'especialidad_id' => $this->especialidad->id,
            'cantidad_cupos' => 2,
        ]);

        $first = $this->postJson('/api/citas', $this->citaPayload($fecha));
        $second = $this->postJson('/api/citas', $this->citaPayload($fecha));
        $rejected = $this->postJson('/api/citas', $this->citaPayload($fecha));

        $first->assertCreated()->assertJsonPath('data.nro_ticket', 1);
        $second->assertCreated()->assertJsonPath('data.nro_ticket', 2);
        $rejected->assertStatus(400)->assertJsonPath('errors.error_code', 'cupos_llenos');
        $this->assertSame(2, DB::table('citas')->whereDate('fecha', $fecha)->count());
    }

    public function test_database_rejects_duplicate_tickets_for_the_same_specialty_and_date(): void
    {
        $fecha = now()->addDay()->toDateString();
        $attributes = [
            'paciente_id' => $this->paciente->id,
            'personal_salud_id' => $this->personalSalud->id,
            'especialidad_id' => $this->especialidad->id,
            'fecha' => $fecha,
            'hora' => null,
            'operador_id' => $this->operador->id,
            'nro_ticket' => 1,
        ];

        DB::table('citas')->insert($attributes);

        $this->expectException(QueryException::class);
        DB::table('citas')->insert($attributes);
    }

    public function test_ticket_sequences_are_independent_for_each_specialty(): void
    {
        $fecha = now()->addDay()->toDateString();
        $otraEspecialidad = Especialidad::create([
            'UPS' => 'PED',
            'especialidad' => 'Pediatria',
        ]);
        $otroProfesional = PersonalSalud::create([
            'nombres' => 'Otro profesional',
            'apellidos' => 'Sintetico',
            'dni' => '44332211',
            'email' => 'otro-profesional@example.test',
            'especialidad_id' => $otraEspecialidad->id,
        ]);

        $primeraEspecialidad = $this->postJson('/api/citas', $this->citaPayload($fecha));
        $segundaEspecialidad = $this->postJson('/api/citas', array_merge(
            $this->citaPayload($fecha),
            [
                'especialidad_id' => $otraEspecialidad->id,
                'personal_salud_id' => $otroProfesional->id,
            ]
        ));

        $primeraEspecialidad->assertCreated()->assertJsonPath('data.nro_ticket', 1);
        $segundaEspecialidad->assertCreated()->assertJsonPath('data.nro_ticket', 1);
    }

    public function test_parallel_requests_cannot_overbook_a_specialty(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Parallel booking test requires PostgreSQL and pcntl.');
        }

        $fecha = now()->addDay()->toDateString();
        CuposPorEspecialidad::create([
            'fecha' => $fecha,
            'especialidad_id' => $this->especialidad->id,
            'cantidad_cupos' => 1,
        ]);
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
                $response = $this->postJson('/api/citas', $this->citaPayload($fecha));
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
        $parentResponse = $this->postJson('/api/citas', $this->citaPayload($fecha));
        $childResult = json_decode(stream_get_contents($parentSocket), true);
        fclose($parentSocket);
        pcntl_waitpid($pid, $status);

        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertIsArray($childResult);
        $statuses = [$parentResponse->status(), $childResult['status']];
        $this->assertSame(1, count(array_filter($statuses, fn ($status) => $status === 201)));
        $this->assertSame(1, count(array_filter($statuses, fn ($status) => $status === 400)));
        $this->assertSame(1, DB::table('citas')->whereDate('fecha', $fecha)->count());
    }

    private function citaPayload(string $fecha): array
    {
        return [
            'paciente_id' => $this->paciente->id,
            'personal_salud_id' => $this->personalSalud->id,
            'especialidad_id' => $this->especialidad->id,
            'fecha' => $fecha,
            'hora' => null,
            'operador_id' => $this->operador->id,
            'nro_ticket' => 1,
        ];
    }
}
