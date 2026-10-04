<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paradas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->decimal('latitud', 9, 6);
            $tabla->decimal('longitud', 9, 6);
            $tabla->timestamps();

            // Para filtrar por el rectángulo que se ve en el mapa (M3).
            $tabla->index(['latitud', 'longitud']);
        });

        // Qué paradas tiene cada ramal, en orden, y a cuántos metros del inicio cae cada una.
        Schema::create('parada_ramal', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('ramal_id')->constrained('ramales')->cascadeOnDelete();
            $tabla->foreignId('parada_id')->constrained('paradas')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('orden');
            $tabla->unsignedInteger('distancia_m');

            $tabla->unique(['ramal_id', 'orden']);
            $tabla->unique(['ramal_id', 'parada_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parada_ramal');
        Schema::dropIfExists('paradas');
    }
};
