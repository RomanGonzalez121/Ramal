<?php

use Illuminate\Support\Facades\Schedule;

// El programador de Laravel corre una vez por minuto; el comando simula durante casi todo ese minuto
// (un tick cada pocos segundos) y el siguiente arranca cuando este termina. En desarrollo alcanza con
// `php artisan ramal:simular`, que corre sin parar.
Schedule::command('ramal:simular --durante=58')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Retención del historial (M8): una vez por hora se borra lo que pasó de las horas que se conservan.
Schedule::command('ramal:limpiar-historial')->hourly();
