<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una sola fila: con qué semilla corre la simulación y en qué tick va.
        Schema::create('simulacion', function (Blueprint $tabla) {
            $tabla->unsignedTinyInteger('id')->primary();
            $tabla->unsignedBigInteger('semilla');
            $tabla->unsignedBigInteger('tick')->default(0);
            $tabla->timestamp('ultimo_tick_en', 3)->nullable();
            $tabla->timestamps();
        });

        // Dónde está cada colectivo ahora mismo. Una fila por colectivo, se pisa en cada tick.
        Schema::create('posiciones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('colectivo_id')->unique()->constrained('colectivos')->cascadeOnDelete();
            $tabla->foreignId('ramal_id')->constrained('ramales')->cascadeOnDelete();
            $tabla->double('distancia_m');                  // metros desde el inicio del ramal
            $tabla->float('velocidad_ms');
            $tabla->string('estado', 24);                   // circulando, en_parada, en_terminal, demorado, averiado, fuera_de_servicio
            $tabla->float('espera_s')->default(0);
            $tabla->unsignedSmallInteger('ultima_parada')->default(0);
            $tabla->float('velocidad_crucero');
            $tabla->string('incidente_tipo', 16)->nullable();
            $tabla->float('incidente_restante_s')->default(0);
            $tabla->decimal('latitud', 9, 6);
            $tabla->decimal('longitud', 9, 6);
            $tabla->smallInteger('rumbo');                  // grados, 0 al este, creciendo hacia el sur
            $tabla->unsignedBigInteger('tick');
            $tabla->timestamp('actualizado_en', 3)->nullable();

            $tabla->index(['latitud', 'longitud']);
        });

        // Los incidentes que genera la simulación. M7 completa su ciclo de vida (atendido, resuelto).
        Schema::create('incidentes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('colectivo_id')->constrained('colectivos')->cascadeOnDelete();
            $tabla->enum('tipo', ['demora', 'desvio', 'falla']);
            $tabla->enum('estado', ['activo', 'resuelto'])->default('activo');
            $tabla->unsignedInteger('duracion_prevista_s');
            $tabla->timestamp('inicio_en')->useCurrent();
            $tabla->timestamp('fin_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['estado', 'inicio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentes');
        Schema::dropIfExists('posiciones');
        Schema::dropIfExists('simulacion');
    }
};
