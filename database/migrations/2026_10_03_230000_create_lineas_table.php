<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lineas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->unsignedTinyInteger('numero')->unique(); // 1 a 5: también elige el color --linea-N
            $tabla->string('nombre');
            $tabla->string('destino');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas');
    }
};
