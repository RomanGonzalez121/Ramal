<?php

return [
    // Misma semilla, misma simulación.
    'semilla' => (int) env('RAMAL_SEMILLA', 20260),

    // Segundos entre dos posiciones que se informan.
    'intervalo_s' => (float) env('RAMAL_INTERVALO', 2),

    // En la demo publicada tiene que haber colectivos a cualquier hora. Con false se respetan los horarios.
    'siempre_en_servicio' => (bool) env('RAMAL_SIEMPRE_EN_SERVICIO', true),

    'zona_horaria' => 'America/Argentina/Buenos_Aires',

    // Muestra la cuenta de operador de demostración en la pantalla de ingreso. Apagarlo en una instalación real.
    'mostrar_cuenta_demo' => (bool) env('RAMAL_MOSTRAR_CUENTA_DEMO', true),

    // Rectángulo de la ciudad [oeste, sur, este, norte] y cómo se divide para emitir solo lo que se ve.
    'ciudad' => [
        'caja' => [-60.62, -31.82, -60.42, -31.66],
        'columnas' => 4,
        'filas' => 4,
    ],
];
