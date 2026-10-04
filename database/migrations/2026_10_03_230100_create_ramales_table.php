<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ramales', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('linea_id')->constrained('lineas')->cascadeOnDelete();
            $tabla->enum('sentido', ['ida', 'vuelta']);
            $tabla->string('destino');
            $tabla->timestamps();

            $tabla->unique(['linea_id', 'sentido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramales');
    }
};
