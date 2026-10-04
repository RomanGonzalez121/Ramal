<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una fila por foto (cada ~10 s) con TODOS los colectivos en un bloque binario de 15 bytes por colectivo.
        // Así un día entero pesa unos 5 MB en vez de millones de filas (ver App\Historial\Instantanea).
        Schema::create('historial_posiciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->timestamp('momento')->index();
            $tabla->unsignedBigInteger('tick');
            $tabla->unsignedSmallInteger('cantidad');
            $tabla->binary('datos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_posiciones');
    }
};
