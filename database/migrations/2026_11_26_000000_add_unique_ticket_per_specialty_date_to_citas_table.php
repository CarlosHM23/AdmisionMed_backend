<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->unique(['fecha', 'especialidad_id', 'nro_ticket'], 'citas_fecha_especialidad_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table) {
            $table->dropUnique('citas_fecha_especialidad_ticket_unique');
        });
    }
};
