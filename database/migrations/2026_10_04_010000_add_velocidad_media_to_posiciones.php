<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posiciones', function (Blueprint $tabla) {
            // Velocidad media reciente (promedio móvil de ~60 s, solo mientras se mueve). La usa la estimación de llegada.
            $tabla->float('velocidad_media_ms')->default(0)->after('velocidad_ms');
        });
    }

    public function down(): void
    {
        Schema::table('posiciones', function (Blueprint $tabla) {
            $tabla->dropColumn('velocidad_media_ms');
        });
    }
};
