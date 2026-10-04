<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un camino alternativo para ir de una parada a la siguiente, por otras calles.
        Schema::create('desvios', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('ramal_id')->constrained('ramales')->cascadeOnDelete();
            $tabla->unsignedTinyInteger('desde_orden');     // sale de la parada con este orden y vuelve al recorrido en la siguiente
            $tabla->json('puntos');                          // [[longitud, latitud], ...]
            $tabla->json('distancias');                      // metros acumulados del camino alternativo
            $tabla->unsignedInteger('largo_m');
            $tabla->timestamps();

            $tabla->unique(['ramal_id', 'desde_orden']);
        });

        Schema::table('posiciones', function (Blueprint $tabla) {
            // Si el colectivo tiene un desvío en curso: el tramo (por el orden de su parada de salida) que va a hacer por otras calles.
            $tabla->unsignedTinyInteger('desvio_orden')->nullable()->after('incidente_restante_s');
        });

        // Ciclo del incidente: activo (se generó), atendido (un operador lo tomó), resuelto.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite (la base del plan gratuito, que se rehace al despertar) no tiene ENUM ni MODIFY: texto común.
            Schema::table('incidentes', fn (Blueprint $tabla) => $tabla->string('estado', 10)->default('activo')->change());
        } else {
            DB::statement("ALTER TABLE incidentes MODIFY estado ENUM('activo','atendido','resuelto') NOT NULL DEFAULT 'activo'");
        }

        Schema::table('incidentes', function (Blueprint $tabla) {
            $tabla->unsignedTinyInteger('desvio_orden')->nullable()->after('duracion_prevista_s');
            $tabla->foreignId('atendido_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $tabla->timestamp('atendido_en')->nullable()->after('atendido_por');
            // La atención se aplica en el próximo tick del simulador (es quien mueve a los colectivos).
            $tabla->boolean('ajuste_aplicado')->default(false)->after('atendido_en');
            $tabla->string('accion_pedida', 12)->nullable()->after('ajuste_aplicado');
            $tabla->string('resuelto_por', 12)->nullable()->after('accion_pedida');   // 'simulacion' o 'operador'
        });
    }

    public function down(): void
    {
        Schema::table('incidentes', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('atendido_por');
            $tabla->dropColumn(['desvio_orden', 'atendido_en', 'ajuste_aplicado', 'accion_pedida', 'resuelto_por']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE incidentes MODIFY estado ENUM('activo','resuelto') NOT NULL DEFAULT 'activo'");
        }

        Schema::table('posiciones', function (Blueprint $tabla) {
            $tabla->dropColumn('desvio_orden');
        });

        Schema::dropIfExists('desvios');
    }
};
