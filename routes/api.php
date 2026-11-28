<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
Use App\Http\Controllers\CitasController;
Use App\Http\Controllers\PacientesController;
Use App\Http\Controllers\ReporteController;
Use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EspecialidadesController;
Use App\Http\Controllers\PersonaSaludController;
Use App\Http\Controllers\OperadoresController;
Use App\Http\Controllers\UsersController;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
//Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');


Route::middleware('auth:api')->group(function(){

    Route::get('me', function() {
    $user = auth('api')->user();

    if ($user) {
        // Cargamos la relación para que no sea null
        $user->load('operador'); 
        
        // Agregamos el operador_id directamente al objeto user
        // Así el front recibe el objeto tal cual lo esperaba + el nuevo campo
        $user->operador_id = $user->operador?->id;
    }

    return response()->json($user);
});

    // Endpoints de Perfil de Usuario
    Route::get('user/profile', [App\Http\Controllers\UserProfileController::class, 'show']);
    Route::put('user/profile', [App\Http\Controllers\UserProfileController::class, 'update']);
    Route::post('user/change-password', [App\Http\Controllers\UserProfileController::class, 'changePassword']);
});

Route::middleware(['auth:api'])->group(function () {

    // ACCESO COMPARTIDO (Operador, Supervisor y Personal de Salud)
    
    Route::middleware(['role:operador|supervisor|personal_salud'])->group(function () {
        
        Route::get('pacientes/next-hc', [PacientesController::class, 'getNextHC'])
            ->middleware('role:operador|supervisor');

        
        Route::apiResource('pacientes', PacientesController::class)->only(['index', 'show']);
        Route::apiResource('citas', CitasController::class)->only(['index', 'show']);
        
        Route::get('reportes/personal', [ReporteController::class, 'index']);
        Route::get('reportes/personal/{id}/pacientes', [ReporteController::class, 'showPacientes']);
    });

    
    // OPERADOR Y SUPERVISOR
   
    Route::middleware(['role:operador|supervisor'])->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        
        Route::apiResource('pacientes', PacientesController::class)->except(['index', 'show']);

        // CRUD completo de Personal de Salud y Citas
        Route::apiResource('personal_salud', PersonaSaludController::class);
        Route::get('citas/next-ticket', [CitasController::class, 'getNextTicket']);
        Route::post('citas/cupos-config', [CitasController::class, 'storeCuposConfig']);
        Route::apiResource('citas', CitasController::class)->except(['index', 'show']);

        Route::get('operadores/me', function () {
            return response()->json(auth('api')->user()->operador ?? null);
        });
    });

    // SUPERVISOR
    Route::middleware(['role:supervisor'])->group(function () {
        Route::apiResource('especialidades', EspecialidadesController::class);
        Route::apiResource('operadores', OperadoresController::class);
        Route::get('users', [UsersController::class, 'index']);
        Route::get('admin/dashboard', function () {
            return ['ok' => 'admin'];
        });
    });
});
