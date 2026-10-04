<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorridos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('ramal_id')->unique()->constrained('ramales')->cascadeOnDelete();
            $tabla->json('puntos');      // [[longitud, latitud], ...] en el orden de GeoJSON
            $tabla->json('distancias');  // metros acumulados hasta cada punto; el primero es 0
            $tabla->unsignedInteger('largo_m');
            $tabla->string('fuente');
            $tabla->date('calculado_el');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorridos');
    }
};
