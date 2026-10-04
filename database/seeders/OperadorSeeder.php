<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * La cuenta de operador de la demostración. La contraseña sale de RAMAL_OPERADOR_PASSWORD;
 * si no está definida se usa una de demostración, que no sirve para nada fuera de este proyecto.
 */
class OperadorSeeder extends Seeder
{
    public const CORREO_DEMO = 'operador@ramal.test';

    public const CLAVE_DEMO = 'ramal-demo-2026';

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => self::CORREO_DEMO],
            [
                'name' => 'Operadora de demostración',
                'password' => env('RAMAL_OPERADOR_PASSWORD', self::CLAVE_DEMO),
                'rol' => User::ROL_OPERADOR,
            ],
        );
    }
}
