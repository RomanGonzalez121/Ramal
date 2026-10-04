<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve el archivo PMTiles del mapa. PMTiles lee solo los pedacitos que necesita con pedidos de rango HTTP
 * ("dame los bytes 1000 a 5000"), y el servidor de desarrollo de PHP no los entiende; esta respuesta sí.
 * En producción conviene que lo sirva directamente el servidor web (nginx o Apache), que también soportan rangos.
 */
class TeselasController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $ruta = resource_path('mapa/parana.pmtiles');

        abort_unless(is_file($ruta), 404, 'Falta resources/mapa/parana.pmtiles. Ver scripts/descargar-mapa.sh.');

        return response()->file($ruta, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
