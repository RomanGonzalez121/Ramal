<?php

// Fuente de la tabla de colores de /identidad. Los mismos valores están en
// resources/css/app.css; tests/Unit/IdentidadTest.php comprueba que coinciden.

return [
    'papel' => '#F7F7F4',
    'asfalto' => '#1E2328',

    'colores' => [
        ['nombre' => 'Papel', 'superficie' => true, 'dia' => '#F7F7F4', 'noche' => null, 'uso' => 'Fondo en Día'],
        ['nombre' => 'Asfalto', 'superficie' => true, 'dia' => '#1E2328', 'noche' => '#1E2328', 'uso' => 'Texto en Día; fondo en Noche'],
        ['nombre' => 'Calzada', 'superficie' => true, 'dia' => '#2B3138', 'noche' => '#2B3138', 'uso' => 'Superficies elevadas en Noche'],
        ['nombre' => 'Cordón', 'superficie' => true, 'dia' => '#ECEDE8', 'noche' => null, 'uso' => 'Superficies y divisiones en Día'],
        ['nombre' => 'Señal', 'dia' => '#F5C400', 'noche' => '#F5C400', 'uso' => 'La parada. Marca lo que está en vivo'],
        ['nombre' => 'Bordó', 'dia' => '#9E2A3C', 'noche' => '#F0788C', 'uso' => 'Línea 1', 'linea' => 1],
        ['nombre' => 'Ultramar', 'dia' => '#2F4CB3', 'noche' => '#8BA0FF', 'uso' => 'Línea 2', 'linea' => 2],
        ['nombre' => 'Verde ruta', 'dia' => '#16764B', 'noche' => '#4CC38A', 'uso' => 'Línea 3', 'linea' => 3],
        ['nombre' => 'Naranja', 'dia' => '#B94A0B', 'noche' => '#FF9A55', 'uso' => 'Línea 4', 'linea' => 4],
        ['nombre' => 'Violeta', 'dia' => '#7A3FA0', 'noche' => '#C08BE6', 'uso' => 'Línea 5', 'linea' => 5],
        ['nombre' => 'Gris de apoyo', 'dia' => '#5B6168', 'noche' => '#9AA1A8', 'uso' => 'Texto secundario'],
    ],
];
