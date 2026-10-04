<?php

namespace App\Http\Controllers;

use App\Api\Documentacion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * La página de documentación de la API pública y el documento OpenAPI que la alimenta.
 */
class ApiDocumentacionController extends Controller
{
    public function pagina(Documentacion $doc): View
    {
        return view('api', [
            'doc' => $doc,
            'grupos' => $doc->porEtiqueta(),
            'limite' => config('ramal.api.limite_por_minuto'),
        ]);
    }

    public function yaml(): Response
    {
        return response(file_get_contents(resource_path('api/openapi.yaml')), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function json(Documentacion $doc): JsonResponse
    {
        return response()->json($doc->documento(), 200, ['Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
