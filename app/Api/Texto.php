<?php

namespace App\Api;

use Illuminate\Support\HtmlString;

/**
 * Convierte el texto de las descripciones del documento OpenAPI (un Markdown mínimo: párrafos, **negrita** y `código`)
 * en HTML seguro. Todo lo que no es esa sintaxis se escapa.
 */
final class Texto
{
    public static function html(?string $texto): HtmlString
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return new HtmlString('');
        }

        $parrafos = preg_split('/\n\s*\n/', $texto);

        $html = array_map(function (string $parrafo) {
            $parrafo = e(preg_replace('/\s*\n\s*/', ' ', trim($parrafo)));
            $parrafo = preg_replace('/`([^`]+)`/', '<code>$1</code>', $parrafo);
            $parrafo = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $parrafo);

            return '<p>'.$parrafo.'</p>';
        }, $parrafos);

        return new HtmlString(implode("\n", $html));
    }
}
