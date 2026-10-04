<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            // 'operador' puede entrar al panel de control. Cualquier otro valor no.
            $tabla->string('rol', 20)->default('visitante')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn('rol');
        });
    }
};
