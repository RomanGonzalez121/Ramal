<?php

namespace App\Api;

use Symfony\Component\Yaml\Yaml;

/**
 * Lee el documento OpenAPI (resources/api/openapi.yaml) y lo deja listo para dibujar la página de documentación:
 * resuelve las referencias `$ref`, ordena los endpoints por etiqueta y arma un ejemplo de respuesta a partir del esquema.
 *
 * El documento es la única fuente de verdad: la página, el JSON descargable y el test que compara contra las rutas salen de él.
 */
class Documentacion
{
    public const METODOS = ['get', 'post', 'put', 'patch', 'delete'];

    /** @var array<string, mixed> */
    private array $documento;

    public function __construct(?string $ruta = null)
    {
        $this->documento = Yaml::parseFile($ruta ?? resource_path('api/openapi.yaml'));
    }

    /** @return array<string, mixed> */
    public function documento(): array
    {
        return $this->documento;
    }

    public function titulo(): string
    {
        return $this->documento['info']['title'];
    }

    public function version(): string
    {
        return $this->documento['info']['version'];
    }

    public function descripcion(): string
    {
        return $this->documento['info']['description'] ?? '';
    }

    public function servidor(): string
    {
        return $this->documento['servers'][0]['url'];
    }

    /** @return array<int, array{nombre: string, descripcion: string}> */
    public function etiquetas(): array
    {
        return array_map(fn ($e) => ['nombre' => $e['name'], 'descripcion' => $e['description'] ?? ''], $this->documento['tags'] ?? []);
    }

    /**
     * Cada operación (método + ruta) con todo lo que la página necesita ya resuelto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function operaciones(): array
    {
        $salida = [];

        foreach ($this->documento['paths'] as $ruta => $item) {
            foreach (self::METODOS as $metodo) {
                if (! isset($item[$metodo])) {
                    continue;
                }

                $op = $item[$metodo];
                $requiereToken = array_key_exists('security', $op) ? $op['security'] !== [] : ($this->documento['security'] ?? []) !== [];

                $respuestas = [];
                foreach ($op['responses'] as $codigo => $respuesta) {
                    $respuesta = $this->resolver($respuesta);
                    $medio = $respuesta['content']['application/json'] ?? [];
                    $esquema = $medio['schema'] ?? null;

                    $respuestas[] = [
                        'codigo' => (string) $codigo,
                        'descripcion' => $respuesta['description'] ?? '',
                        'esquema' => $esquema ? $this->resolverProfundo($esquema) : null,
                        'ejemplo' => $medio['example'] ?? ($esquema ? $this->ejemplo($esquema) : null),
                    ];
                }

                $salida[] = [
                    'id' => $op['operationId'],
                    'metodo' => strtoupper($metodo),
                    'ruta' => $ruta,
                    'etiqueta' => $op['tags'][0] ?? 'General',
                    'resumen' => $op['summary'] ?? '',
                    'descripcion' => $op['description'] ?? '',
                    'requiere_token' => $requiereToken,
                    'parametros' => array_map(fn ($p) => $this->parametro($this->resolver($p)), $op['parameters'] ?? []),
                    'respuestas' => $respuestas,
                ];
            }
        }

        return $salida;
    }

    /** Las operaciones agrupadas por etiqueta, en el orden en que se declararon las etiquetas. */
    public function porEtiqueta(): array
    {
        $operaciones = $this->operaciones();
        $grupos = [];

        foreach ($this->etiquetas() as $etiqueta) {
            $grupos[] = [
                ...$etiqueta,
                'operaciones' => array_values(array_filter($operaciones, fn ($o) => $o['etiqueta'] === $etiqueta['nombre'])),
            ];
        }

        return $grupos;
    }

    /** Pares método + ruta documentados, con las rutas pasadas a una forma comparable ("/lineas/{}"). */
    public function firmas(): array
    {
        $firmas = [];
        foreach ($this->operaciones() as $op) {
            $firmas[] = $op['metodo'].' '.preg_replace('/\{[^}]+\}/', '{}', $this->servidor().$op['ruta']);
        }
        sort($firmas);

        return $firmas;
    }

    private function parametro(array $p): array
    {
        $esquema = $this->resolverProfundo($p['schema'] ?? []);

        return [
            'nombre' => $p['name'],
            'en' => $p['in'],
            'obligatorio' => (bool) ($p['required'] ?? false),
            'descripcion' => $p['description'] ?? '',
            'tipo' => $this->tipo($esquema),
            'ejemplo' => $esquema['examples'][0] ?? $esquema['default'] ?? ($esquema['enum'][0] ?? null),
            'opciones' => $esquema['enum'] ?? null,
        ];
    }

    /** Sigue un `$ref` ("#/components/...") hasta el objeto real. */
    public function resolver(array $nodo): array
    {
        $guardia = 0;
        while (isset($nodo['$ref']) && $guardia++ < 10) {
            $nodo = $this->apuntado($nodo['$ref']);
        }

        return $nodo;
    }

    /** Igual que `resolver`, pero también adentro: propiedades, items, etc. */
    public function resolverProfundo(array $nodo, int $nivel = 0): array
    {
        $nodo = $this->resolver($nodo);

        if ($nivel > 8) {
            return $nodo;
        }

        if (isset($nodo['properties'])) {
            foreach ($nodo['properties'] as $clave => $hijo) {
                $nodo['properties'][$clave] = $this->resolverProfundo($hijo, $nivel + 1);
            }
        }

        if (isset($nodo['items'])) {
            $nodo['items'] = $this->resolverProfundo($nodo['items'], $nivel + 1);
        }

        if (isset($nodo['additionalProperties']) && is_array($nodo['additionalProperties'])) {
            $nodo['additionalProperties'] = $this->resolverProfundo($nodo['additionalProperties'], $nivel + 1);
        }

        return $nodo;
    }

    /** Texto corto del tipo de un esquema: "entero", "lista de texto", "texto o nulo"... */
    public function tipo(array $esquema): string
    {
        $esquema = $this->resolver($esquema);
        $tipos = (array) ($esquema['type'] ?? (isset($esquema['enum']) ? 'string' : 'object'));
        $acepta_nulo = in_array('null', $tipos, true);
        $tipos = array_values(array_filter($tipos, fn ($t) => $t !== 'null'));
        $tipo = $tipos[0] ?? 'object';

        $nombre = match ($tipo) {
            'integer' => 'entero',
            'number' => 'número',
            'string' => match ($esquema['format'] ?? null) {
                'date-time' => 'fecha y hora',
                default => isset($esquema['enum']) ? 'uno de' : 'texto',
            },
            'boolean' => 'verdadero o falso',
            'array' => 'lista de '.$this->tipo($esquema['items'] ?? ['type' => 'object']),
            default => 'objeto',
        };

        return $acepta_nulo ? $nombre.' o nulo' : $nombre;
    }

    /** Un ejemplo de valor para un esquema, usando los `examples`, `enum` y `default` del documento cuando existen. */
    public function ejemplo(array $esquema, int $nivel = 0): mixed
    {
        $esquema = $this->resolver($esquema);

        if ($nivel > 8) {
            return null;
        }

        if (isset($esquema['examples'][0])) {
            return $esquema['examples'][0];
        }

        if (array_key_exists('const', $esquema)) {
            return $esquema['const'];
        }

        if (isset($esquema['enum'])) {
            return $esquema['enum'][0];
        }

        $tipos = array_values(array_filter((array) ($esquema['type'] ?? 'object'), fn ($t) => $t !== 'null'));

        return match ($tipos[0] ?? 'object') {
            'object' => $this->ejemploDeObjeto($esquema, $nivel),
            'array' => isset($esquema['prefixItems'])
                ? array_map(fn ($i) => $this->ejemplo($i, $nivel + 1), $esquema['prefixItems'])
                : [$this->ejemplo($esquema['items'] ?? ['type' => 'string'], $nivel + 1)],
            'integer' => $esquema['default'] ?? 1,
            'number' => $esquema['default'] ?? 0.5,
            'boolean' => false,
            default => ($esquema['format'] ?? null) === 'date-time' ? '2026-10-04T10:30:00-03:00' : 'texto',
        };
    }

    private function ejemploDeObjeto(array $esquema, int $nivel): array
    {
        $objeto = [];
        foreach ($esquema['properties'] ?? [] as $clave => $hijo) {
            $objeto[$clave] = $this->ejemplo($hijo, $nivel + 1);
        }

        return $objeto;
    }

    private function apuntado(string $ref): array
    {
        $nodo = $this->documento;
        foreach (explode('/', ltrim($ref, '#/')) as $parte) {
            $nodo = $nodo[$parte] ?? throw new \RuntimeException("El documento OpenAPI apunta a {$ref}, que no existe.");
        }

        return $nodo;
    }
}
