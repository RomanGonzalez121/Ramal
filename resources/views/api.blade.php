@extends('layouts.base')

@php
    use App\Api\Texto;

    // Qué le pasa al que consulta, dicho con ícono y palabra (el color solo no alcanza).
    $codigos = [
        '200' => ['en-hora', 'Bien'],
        '401' => ['candado', 'Sin token'],
        '403' => ['candado', 'Sin permiso'],
        '404' => ['sin-senal', 'No existe'],
        '422' => ['incidente', 'Dato inválido'],
        '429' => ['reloj', 'Demasiados pedidos'],
    ];
    $cantidad = count($doc->operaciones());
@endphp

@section('titulo', 'API pública de Ramal')
@section('descripcion', 'Líneas, paradas, posiciones en vivo y llegadas de los colectivos de Paraná, con token y documentación OpenAPI.')

@section('cuerpo')
@include('partials.cabecera', ['enlaces' => ['empezar' => 'Empezar', 'referencia' => 'Referencia']])

<main id="contenido" x-data="documentacionApi" class="mx-auto max-w-7xl px-4 pb-24 pt-6 sm:px-8">

    {{-- ===================== PORTADA: el tablero de la terminal ===================== --}}
    <section class="tema-noche tablero tablero-con-calzada relative overflow-hidden rounded-sm" aria-labelledby="titulo-api">
        <div class="grid gap-8 px-6 py-8 sm:px-10 sm:py-12 lg:grid-cols-[1.2fr_1fr] lg:gap-12 lg:px-14">
            <div>
                <p class="entra-campo inline-flex items-center gap-2 rounded-full border-2 px-3 py-1 text-sm font-semibold" style="--i: 0; border-color: color-mix(in srgb, var(--papel) 35%, transparent)" role="status">
                    <template x-if="estado === 'activa'"><span class="flex items-center gap-1.5 text-senal"><x-icono nombre="en-vivo" :tamano="16" /> Servicio activo, <span x-text="colectivos"></span> colectivos</span></template>
                    <template x-if="estado === 'detenida'"><span class="flex items-center gap-1.5"><x-icono nombre="sin-senal" :tamano="16" /> Simulación detenida</span></template>
                    <template x-if="estado === 'cargando'"><span class="flex items-center gap-1.5"><x-icono nombre="reloj" :tamano="16" /> Consultando el servicio</span></template>
                    <template x-if="estado === 'error'"><span class="flex items-center gap-1.5"><x-icono nombre="sin-senal" :tamano="16" /> Sin respuesta</span></template>
                </p>

                <h1 id="titulo-api" class="entra-campo parpadeo-panel mt-5 font-panel text-[clamp(2.8rem,7vw,5.6rem)] font-bold uppercase leading-[0.95] text-senal" style="--i: 1">
                    API pública
                </h1>
                <p class="entra-campo mt-5 max-w-[44ch] text-lg text-papel/85" style="--i: 2">
                    Las líneas, las paradas, dónde está cada colectivo ahora y cuándo llega el próximo. {{ $cantidad }} consultas de lectura, un token y nada más.
                </p>

                <div class="entra-campo mt-6 flex flex-wrap items-center gap-3" style="--i: 3">
                    <a href="{{ route('api.json') }}" class="cartel-parada flex items-center gap-2 rounded-sm px-4 py-2 font-titulo text-xl font-extrabold leading-none"><x-icono nombre="api" :tamano="20" /> OpenAPI en JSON</a>
                    <a href="{{ route('api.yaml') }}" class="flex items-center gap-2 rounded-sm border-2 px-4 py-2 font-titulo text-xl font-extrabold leading-none hover:bg-papel hover:text-asfalto" style="border-color: color-mix(in srgb, var(--papel) 45%, transparent)">YAML</a>
                </div>
            </div>

            {{-- El recorrido de tres paradas: cómo empezar, dibujado como un plano de línea --}}
            <ol id="empezar" class="relative space-y-6 self-center" aria-label="Cómo empezar en tres pasos">
                <span class="absolute bottom-3 left-[0.95rem] top-3 w-1 bg-senal" aria-hidden="true" style="background: repeating-linear-gradient(180deg, var(--senal) 0 14px, transparent 14px 24px)"></span>
                @foreach ([
                    ['Pedí un token', 'Entrá al panel de operador, abrí "Acceso a la API" y creá uno. Se muestra una sola vez.', route('operador.api'), 'Ir a mis tokens'],
                    ['Mandalo en la cabecera', 'Authorization: Bearer <tu token>. Cada token tiene '.$limite.' pedidos por minuto.', null, null],
                    ['Leé los colectivos', 'GET '.$doc->servidor().'/colectivos devuelve los 40 con su posición, rumbo y estado.', null, null],
                ] as $i => [$titulo, $texto, $enlace, $enlaceTexto])
                    <li class="entra-campo relative flex gap-4" style="--i: {{ $i + 2 }}">
                        <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-[3px] border-senal bg-asfalto font-titulo text-lg font-black leading-none text-senal">{{ $i + 1 }}</span>
                        <div class="min-w-0">
                            <p class="font-titulo text-[1.7rem] font-extrabold leading-none">{{ $titulo }}</p>
                            <p class="mt-1.5 text-papel/80">{{ $texto }}</p>
                            @if ($enlace)
                                <a href="{{ $enlace }}" class="mt-2 inline-flex items-center gap-1.5 font-semibold text-senal underline decoration-2 underline-offset-4"><x-icono nombre="operador" :tamano="18" /> {{ $enlaceTexto }}</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="calzada-discontinua mx-6 text-senal sm:mx-10 lg:mx-14" aria-hidden="true"></div>

        <dl class="grid gap-x-8 gap-y-3 px-6 py-5 text-sm sm:grid-cols-3 sm:px-10 lg:px-14">
            <div><dt class="font-semibold text-papel/70">Dirección base</dt><dd class="mt-0.5 break-all font-semibold" x-text="base">{{ $doc->servidor() }}</dd></div>
            <div><dt class="font-semibold text-papel/70">Cupo por token</dt><dd class="mt-0.5 font-semibold">{{ $limite }} pedidos por minuto</dd></div>
            <div><dt class="font-semibold text-papel/70">Errores</dt><dd class="mt-0.5 font-semibold">Siempre con <span class="codigo">{ "mensaje": "..." }</span></dd></div>
        </dl>
    </section>

    {{-- ===================== TU TOKEN: para probar desde la página ===================== --}}
    <section class="entra-campo z-20 mt-6 lg:sticky lg:top-2 border-[3px] border-texto bg-fondo px-3 py-2 sm:px-4" style="--i: 5" aria-label="Tu token para probar">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <label for="token" class="flex items-center gap-2 font-semibold"><x-icono nombre="candado" :tamano="22" /> Tu token</label>
            <div class="campo min-w-0 flex-1" style="min-width: min(100%, 16rem)">
                <input id="token" :type="verToken ? 'text' : 'password'" x-model="token" autocomplete="off" spellcheck="false" placeholder="Pegá acá tu token para usar los botones Probar">
                <button type="button" @click="verToken = !verToken" class="shrink-0 p-1" :aria-label="verToken ? 'Ocultar el token' : 'Mostrar el token'" :aria-pressed="verToken">
                    <span x-show="!verToken"><x-icono nombre="ojo" :tamano="22" /></span>
                    <span x-show="verToken" x-cloak><x-icono nombre="ojo-cerrado" :tamano="22" /></span>
                </button>
            </div>
            <p class="text-sm text-apoyo" x-show="token" x-cloak>Se guarda solo en este navegador.</p>
            <p class="text-sm text-apoyo" x-show="!token">Sin token, solo funciona <span class="font-semibold text-texto">Estado del servicio</span>.</p>
        </div>
    </section>

    {{-- ===================== REFERENCIA ===================== --}}
    <div id="referencia" class="mt-10 grid gap-10 lg:grid-cols-[14rem_1fr]">

        <nav class="hidden lg:block" aria-label="Endpoints">
            <div class="sticky top-28 space-y-5">
                @foreach ($grupos as $grupo)
                    <div>
                        <p class="font-titulo text-xl font-extrabold leading-none">{{ $grupo['nombre'] }}</p>
                        <ul class="mt-2 space-y-1 border-l-[3px] border-dashed border-texto/30 pl-3">
                            @foreach ($grupo['operaciones'] as $op)
                                <li><a href="#{{ $op['id'] }}" @click="abrir('{{ $op['id'] }}')" class="block py-0.5 text-[0.95rem] hover:underline hover:decoration-[3px] hover:underline-offset-4">{{ $op['resumen'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>

        <div class="min-w-0 space-y-12">
            @foreach ($grupos as $g => $grupo)
                <section aria-labelledby="grupo-{{ $g }}">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-texto text-fondo"><x-icono :nombre="['Servicio' => 'en-vivo', 'Líneas' => 'linea', 'Paradas' => 'parada', 'Colectivos' => 'colectivo'][$grupo['nombre']] ?? 'api'" :tamano="24" /></span>
                        <h2 id="grupo-{{ $g }}" class="font-titulo text-[2.6rem] font-black leading-none">{{ $grupo['nombre'] }}</h2>
                    </div>
                    <p class="mt-2 text-apoyo">{{ $grupo['descripcion'] }}</p>
                    <div class="calzada-discontinua mt-3 text-texto/60" aria-hidden="true"></div>

                    <div class="mt-5 space-y-4">
                        @foreach ($grupo['operaciones'] as $op)
                            @php
                                $ok = collect($op['respuestas'])->firstWhere('codigo', '200');
                                $datos = [
                                    'id' => $op['id'],
                                    'ruta' => $op['ruta'],
                                    'requiereToken' => $op['requiere_token'],
                                    'parametros' => array_map(fn ($p) => ['nombre' => $p['nombre'], 'en' => $p['en'], 'ejemplo' => $p['ejemplo'], 'obligatorio' => $p['obligatorio']], $op['parametros']),
                                ];
                            @endphp
                            <article id="{{ $op['id'] }}" x-data="probador(@js($datos))" class="scroll-mt-28 border-[3px] border-texto bg-fondo">
                                <button type="button" @click="alternar('{{ $op['id'] }}')" :aria-expanded="abierto === '{{ $op['id'] }}'" aria-controls="cuerpo-{{ $op['id'] }}"
                                        class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-left transition-colors duration-100 hover:bg-superficie">
                                    <span class="cartel-parada rounded-sm px-3 py-0.5 font-titulo text-xl font-black leading-tight">{{ $op['metodo'] }}</span>
                                    <span class="min-w-0 flex-1 break-all font-titulo text-[1.7rem] font-extrabold leading-none">{{ $op['ruta'] }}</span>
                                    <span class="flex items-center gap-1.5 text-sm font-semibold text-apoyo">
                                        @if ($op['requiere_token']) <x-icono nombre="candado" :tamano="18" /> Con token @else <x-icono nombre="ojo" :tamano="18" /> Abierto @endif
                                    </span>
                                    <span class="basis-full text-[0.95rem] sm:basis-auto sm:text-base">{{ $op['resumen'] }}</span>
                                    <span class="ml-auto" aria-hidden="true"><span x-show="abierto !== '{{ $op['id'] }}'"><x-icono nombre="mas" :tamano="22" /></span><span x-show="abierto === '{{ $op['id'] }}'" x-cloak><x-icono nombre="menos" :tamano="22" /></span></span>
                                </button>

                                <div id="cuerpo-{{ $op['id'] }}" x-show="abierto === '{{ $op['id'] }}'" x-cloak x-transition:enter="transition duration-200 ease-out motion-reduce:duration-75" x-transition:enter-start="opacity-0 -translate-y-1 motion-reduce:translate-y-0" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition duration-100 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="border-t-[3px] border-dashed border-texto/40">
                                    <div class="space-y-6 p-4 sm:p-5">
                                        @if ($op['descripcion'])
                                            <div class="texto-docs max-w-[70ch]">{{ Texto::html($op['descripcion']) }}</div>
                                        @endif

                                        @if ($op['parametros'])
                                            <div>
                                                <h3 class="font-titulo text-2xl font-extrabold leading-none">Parámetros</h3>
                                                <dl class="mt-3 divide-y-2 divide-dashed divide-texto/25 border-y-2 border-dashed border-texto/25">
                                                    @foreach ($op['parametros'] as $p)
                                                        <div class="grid gap-x-4 gap-y-1 py-2.5 sm:grid-cols-[11rem_1fr]">
                                                            <dt class="flex flex-wrap items-baseline gap-x-2">
                                                                <span class="codigo font-bold">{{ $p['nombre'] }}</span>
                                                                <span class="text-sm text-apoyo">{{ $p['en'] === 'path' ? 'en la ruta' : 'en la consulta' }}</span>
                                                            </dt>
                                                            <dd class="text-[0.97rem]">
                                                                <span class="texto-docs inline">{{ Texto::html($p['descripcion']) }}</span>
                                                                <span class="text-apoyo">({{ $p['tipo'] }}{{ $p['obligatorio'] ? ', obligatorio' : '' }})</span>
                                                                @if ($p['opciones']) <span class="mt-1 flex flex-wrap gap-1.5">@foreach ($p['opciones'] as $opcion)<span class="codigo rounded-sm bg-superficie px-1.5 py-0.5 text-sm">{{ $opcion }}</span>@endforeach</span> @endif
                                                            </dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            </div>
                                        @endif

                                        {{-- Probar de verdad --}}
                                        <div class="tema-noche tablero overflow-hidden rounded-sm">
                                            <div class="flex flex-wrap items-end gap-3 border-b-2 border-dashed border-papel/25 p-4">
                                                @foreach ($op['parametros'] as $p)
                                                    <label class="block text-sm font-semibold text-papel/80">
                                                        <span class="codigo">{{ $p['nombre'] }}</span>@if ($p['obligatorio']) <span class="text-senal">*</span>@endif
                                                        <input type="text" x-model="valores['{{ $p['nombre'] }}']" placeholder="{{ $p['ejemplo'] ?? '' }}" autocomplete="off" spellcheck="false"
                                                               class="mt-1 block w-full min-w-[9rem] rounded-sm border-2 bg-asfalto px-3 py-2 font-normal text-papel placeholder:text-papel/40" style="border-color: color-mix(in srgb, var(--papel) 40%, transparent)">
                                                    </label>
                                                @endforeach
                                                <button type="button" @click="probar()" :disabled="cargando || (requiereToken && !token)"
                                                        class="cartel-parada flex items-center gap-2 rounded-sm px-5 py-2.5 font-titulo text-xl font-extrabold leading-none disabled:cursor-not-allowed disabled:opacity-50">
                                                    <x-icono nombre="reproducir" :tamano="18" /> <span x-text="cargando ? 'Pidiendo...' : 'Probar'">Probar</span>
                                                </button>
                                                <p x-show="requiereToken && !token" x-cloak class="basis-full text-sm text-senal">Pegá tu token arriba para poder probar esta consulta.</p>
                                            </div>

                                            <p class="break-all px-4 pt-3 text-sm text-papel/75"><span class="font-semibold text-papel">{{ $op['metodo'] }}</span> <span class="codigo" x-text="direccion()"></span></p>

                                            <div class="p-4" aria-live="polite">
                                                <template x-if="!respuesta">
                                                    <div>
                                                        <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-papel/70"><x-icono nombre="en-hora" :tamano="18" /> Ejemplo de respuesta</p>
                                                        <pre class="codigo-bloque">{{ json_encode($ok['ejemplo'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                </template>
                                                <template x-if="respuesta">
                                                    <div>
                                                        <p class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm font-semibold">
                                                            <span class="flex items-center gap-1.5 rounded-full px-3 py-0.5" :class="respuesta.ok ? 'bg-senal text-asfalto' : 'border-2 border-senal text-senal'">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><use :href="'#i-' + respuesta.icono"/></svg>
                                                                <span x-text="respuesta.estado + ' ' + respuesta.palabra"></span>
                                                            </span>
                                                            <span class="text-papel/75" x-text="respuesta.ms + ' ms'"></span>
                                                            <span class="text-papel/75" x-show="respuesta.restantes !== null" x-text="'Te quedan ' + respuesta.restantes + ' pedidos este minuto'"></span>
                                                        </p>
                                                        <pre class="codigo-bloque" x-text="respuesta.cuerpo"></pre>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <div>
                                            <h3 class="font-titulo text-2xl font-extrabold leading-none">Respuestas</h3>
                                            <ul class="mt-3 space-y-2">
                                                @foreach ($op['respuestas'] as $r)
                                                    @php [$icono, $palabra] = $codigos[$r['codigo']] ?? ['alerta', 'Error']; @endphp
                                                    <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                                        <span class="inline-flex min-w-[10.5rem] items-center gap-1.5 font-semibold {{ $r['codigo'] === '200' ? '' : 'text-apoyo' }}">
                                                            <x-icono :nombre="$icono" :tamano="18" /> <span class="codigo">{{ $r['codigo'] }}</span> {{ $palabra }}
                                                        </span>
                                                        <span class="texto-docs text-[0.97rem]">{{ Texto::html($r['descripcion']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>

                                        @if ($ok && ($ok['esquema']['properties'] ?? false))
                                            <div>
                                                <h3 class="font-titulo text-2xl font-extrabold leading-none">Qué trae la respuesta</h3>
                                                <div class="mt-3 border-y-2 border-dashed border-texto/25">
                                                    @include('partials.esquema', ['esquema' => $ok['esquema'], 'doc' => $doc, 'nivel' => 0])
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</main>

@include('partials.pie')
@endsection
