<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Choferes inventados: el sitio lo dice abiertamente.
        Schema::create('choferes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('legajo')->unique();
            $tabla->timestamps();
        });

        Schema::create('colectivos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('linea_id')->constrained('lineas')->cascadeOnDelete();
            $tabla->foreignId('chofer_id')->nullable()->constrained('choferes')->nullOnDelete();
            $tabla->string('interno')->unique(); // "301" = línea 3, unidad 01
            $tabla->unsignedTinyInteger('capacidad')->default(40);
            $tabla->timestamps();
        });

        Schema::create('horarios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('linea_id')->constrained('lineas')->cascadeOnDelete();
            $tabla->enum('dia', ['habil', 'sabado', 'domingo']);
            $tabla->time('desde');
            $tabla->time('hasta');
            $tabla->unsignedTinyInteger('frecuencia_min');
            $tabla->timestamps();

            $tabla->unique(['linea_id', 'dia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
        Schema::dropIfExists('colectivos');
        Schema::dropIfExists('choferes');
    }
};
