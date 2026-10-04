<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidentes', function (Blueprint $tabla) {
            // 'simulacion': los generó el simulador en vivo. 'relleno': los generó `ramal:rellenar-dia`
            // con el mismo simulador para las horas anteriores a que arrancara el servidor.
            $tabla->string('origen', 12)->default('simulacion')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('incidentes', function (Blueprint $tabla) {
            $tabla->dropColumn('origen');
        });
    }
};
