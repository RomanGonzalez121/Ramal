@extends('layouts.base')

@section('titulo', 'Cómo funciona Ramal')
@section('descripcion', 'Qué es inventado y qué es real en Ramal: cómo se mueven los colectivos, cómo se calcula cuándo llegan y cómo viaja cada posición hasta el mapa.')

@php
    $est = $estimador;
    $hist = $historial;
    $campos = [
        ['id', 'Colectivo', 2],
        ['ramal', 'Ramal', 2],
        ['lon', 'Longitud', 4],
        ['lat', 'Latitud', 4],
        ['rumbo', 'Rumbo', 2],
        ['estado', 'Estado', 1],
    ];
@endphp

@section('cuerpo')
@include('partials.cabecera', ['enlaces' => ['mundo' => 'Qué es real', 'llegada' => 'Llegadas', 'tiempo-real' => 'Tiempo real']])

<main id="contenido" class="mx-auto max-w-7xl px-4 pb-10 pt-6 sm:px-8">

    {{-- ===================== PORTADA ===================== --}}
    <section class="grid gap-8 lg:grid-cols-[1.4fr_1fr] lg:items-end" aria-labelledby="titulo-como">
        <div>
            <h1 id="titulo-como" class="entra-campo font-titulo text-[clamp(3.2rem,9vw,7.5rem)] font-black leading-[0.88]" style="--i: 0">Se simula el mundo, no la tecnología</h1>
        </div>
        <p class="entra-campo max-w-[44ch] text-lg text-apoyo lg:pb-3" style="--i: 1">
            Los colectivos de Ramal no existen. Lo que los sigue, los cuenta y los muestra sí funciona de verdad. Esta página explica dónde está la línea entre una cosa y la otra.
        </p>
    </section>

    {{-- ===================== QUÉ ES REAL ===================== --}}
    <section id="mundo" class="mt-10 grid overflow-hidden rounded-sm border-[3px] border-texto md:grid-cols-2" aria-label="Qué es inventado y qué es real">
        <div class="entra-campo p-6 sm:p-8" style="--i: 2">
            <p class="flex items-center gap-2 font-titulo text-[2.2rem] font-black leading-none"><x-icono nombre="colectivo" :tamano="30" /> Inventado</p>
            <p class="mt-1 text-apoyo">Lo que haría falta una empresa de transporte para tener.</p>
            <ul class="mt-5 space-y-3.5">
                @foreach ([
                    ['linea', 'Las '.$cifras['lineas'].' líneas y sus horarios', 'Los números, los nombres y las frecuencias están pensados. Los recorridos van por calles reales.'],
                    ['colectivo', 'Los '.$cifras['colectivos'].' colectivos y sus choferes', 'Cada uno anda solo, siguiendo reglas simples: acelera, frena en las paradas y espera en las terminales.'],
                    ['demora', 'Los pasajeros', 'No hay nadie arriba. Nadie sube ni baja, por eso no se cuenta cuánta gente viaja.'],
                    ['incidente', 'Los incidentes', 'Cada colectivo sufre una demora cada unos '.$incidentes['demora_horas'].' horas, un desvío cada '.$incidentes['desvio_horas'].' y una falla cada '.$incidentes['falla_horas'].'.'],
                ] as [$icono, $titulo, $texto])
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-sm border-2 border-dashed border-texto"><x-icono :nombre="$icono" :tamano="22" /></span>
                        <div><p class="font-semibold">{{ $titulo }}</p><p class="text-[0.97rem] text-apoyo">{{ $texto }}</p></div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="tema-noche tablero entra-campo border-t-[3px] border-texto p-6 sm:p-8 md:border-l-[3px] md:border-t-0" style="--i: 3; border-color: var(--senal)">
            <p class="flex items-center gap-2 font-titulo text-[2.2rem] font-black leading-none text-senal"><x-icono nombre="en-hora" :tamano="30" /> De verdad</p>
            <p class="mt-1 text-papel/70">Lo que se construyó para este proyecto y funciona.</p>
            <ul class="mt-5 space-y-3.5">
                @foreach ([
                    ['mapa', 'Las calles y el mapa', 'Datos de OpenStreetMap en un archivo propio, con un estilo para el día y otro para la noche.'],
                    ['ramal', 'Los recorridos', 'Se calcularon una vez sobre las calles reales, con sus manos y giros, y quedaron guardados en la base.'],
                    ['en-vivo', 'El tiempo real', 'Cada posición viaja por un servidor de WebSockets. Si se corta, el mapa consulta cada pocos segundos.'],
                    ['reloj', 'El cálculo de llegada', 'Usa la velocidad que viene teniendo cada colectivo, no la que dice el cartel.'],
                    ['api', 'La API y el historial', 'Cualquiera con un token lee los datos. Cada '.$hist['paso_s'].' segundos se guarda una foto y se puede rebobinar.'],
                ] as [$icono, $titulo, $texto])
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-sm bg-senal text-asfalto"><x-icono :nombre="$icono" :tamano="22" /></span>
                        <div><p class="font-semibold">{{ $titulo }}</p><p class="text-[0.97rem] text-papel/75">{{ $texto }}</p></div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ===================== EL COLECTIVO SE MUEVE ===================== --}}
    <section class="mt-16 grid gap-8 lg:grid-cols-[1fr_1.2fr] lg:items-center" aria-labelledby="titulo-mueve">
        <div>
            <div class="flex items-center gap-3">
                <span class="cartel-parada flex h-12 w-12 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="colectivo" :tamano="28" /></span>
                <h2 id="titulo-mueve" class="font-titulo text-[clamp(2.4rem,5vw,3.6rem)] font-black leading-[0.9]">Cómo se mueve un colectivo</h2>
            </div>
            <div class="mt-4 space-y-3 text-lg">
                <p>Cada {{ rtrim(rtrim(number_format($cifras['intervalo_s'], 1, ',', ''), '0'), ',') }} segundos el simulador adelanta a todos los colectivos un pedacito. Acelera hasta una velocidad de crucero que cambia de un colectivo a otro, frena para llegar justo a la parada, espera unos segundos y sigue.</p>
                <p>En la terminal espera más y cambia de sentido: la ida se vuelve vuelta.</p>
                <p class="font-semibold">Nada es al azar de verdad. Con la misma semilla sale exactamente el mismo día, así que cualquier momento se puede reproducir y probar.</p>
            </div>
        </div>

        {{-- Un colectivo de juguete: recorre el ramal, frena en las paradas y espera en las terminales --}}
        <figure class="tema-noche tablero overflow-hidden rounded-sm p-5 sm:p-7" aria-label="Un colectivo recorriendo un ramal: avanza, se detiene en cada parada y espera en las terminales">
            <div class="relative">
                <svg viewBox="0 0 100 34" class="block w-full" role="img" aria-hidden="true">
                    <path d="M4 20H96" stroke="currentColor" stroke-width="1.2" stroke-linecap="square" class="text-papel/40" stroke-dasharray="2 2.4"/>
                    @foreach ([0, 25, 50, 75, 100] as $i => $x)
                        @php $px = 4 + $x * 0.92; @endphp
                        <circle cx="{{ $px }}" cy="20" r="2.4" class="fill-asfalto stroke-papel" stroke-width="1.2"/>
                        @if ($i === 0 || $i === 4)
                            <rect x="{{ $px - 3.2 }}" y="8" width="6.4" height="4.4" class="fill-senal"/>
                            <path d="M{{ $px }} 12.4V17.6" class="stroke-papel" stroke-width="1"/>
                        @endif
                    @endforeach
                    <g class="demo-bus">
                        <rect x="-4.6" y="9.6" width="9.2" height="4.8" rx="0.8" class="fill-senal stroke-asfalto" stroke-width="0.7"/>
                        <rect x="-3.2" y="10.6" width="2" height="2.2" class="fill-asfalto"/>
                        <rect x="-0.4" y="10.6" width="2" height="2.2" class="fill-asfalto"/>
                        <rect x="2.2" y="10.6" width="1.6" height="2.2" class="fill-asfalto"/>
                        <circle cx="-2.4" cy="14.6" r="0.9" class="fill-asfalto"/>
                        <circle cx="2.4" cy="14.6" r="0.9" class="fill-asfalto"/>
                    </g>
                </svg>
            </div>
            <figcaption class="relative mt-3 h-9 font-panel text-[1.7rem] font-bold uppercase leading-none text-senal" aria-hidden="true">
                <span class="demo-estado demo-estado-1 absolute inset-0 flex items-center gap-2"><x-icono nombre="colectivo" :tamano="26" /> Circulando</span>
                <span class="demo-estado demo-estado-2 absolute inset-0 flex items-center gap-2"><x-icono nombre="parada" :tamano="26" /> En parada</span>
                <span class="demo-estado demo-estado-3 absolute inset-0 flex items-center gap-2"><x-icono nombre="reloj" :tamano="26" /> En terminal</span>
            </figcaption>
            <p class="mt-3 text-sm text-papel/70">Parada cada ~{{ 900 }} metros: entre {{ 8 }} y {{ 25 }} segundos detenido. En la terminal, entre 60 y 150.</p>
        </figure>
    </section>

    {{-- ===================== CUÁNDO LLEGA ===================== --}}
    <section id="llegada" class="mt-20" aria-labelledby="titulo-llegada">
        <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr] lg:items-end">
            <div class="flex items-center gap-3">
                <span class="cartel-parada flex h-12 w-12 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="reloj" :tamano="28" /></span>
                <h2 id="titulo-llegada" class="font-titulo text-[clamp(2.4rem,5vw,3.6rem)] font-black leading-[0.9]">Cómo se calcula cuándo llega</h2>
            </div>
            <p class="max-w-[58ch] text-lg">La cuenta no usa horarios: usa lo que está pasando. Toma los metros que le faltan al colectivo, la velocidad con la que viene andando y las paradas del camino. Movelo vos.</p>
        </div>

        <div x-data="calculadoraLlegada(@js(['parada_s' => $est['parada_s'], 'terminal_s' => $est['terminal_s'], 'arreglo_s' => $est['arreglo_s'], 'velocidad_minima_ms' => $est['velocidad_minima_ms'], 'factor_desvio' => $est['factor_desvio']]))"
             class="tema-noche tablero mt-8 grid overflow-hidden rounded-sm lg:grid-cols-[1fr_22rem]">

            <div class="p-5 sm:p-8">
                {{-- El recorrido dibujado: el colectivo, las paradas del camino y la parada de destino --}}
                <svg viewBox="0 0 100 30" class="block w-full" role="img" :aria-label="'El colectivo está a ' + distancia + ' metros de la parada, con ' + paradasEnElMedio + ' paradas en el medio'">
                    <path d="M4 20H96" stroke="currentColor" stroke-width="1.2" stroke-linecap="square" class="text-papel/45" stroke-dasharray="2 2.4"/>
                    <g x-html="paradasSvg"></g>
                    <g transform="translate(96 0)">
                        <path d="M0 20V9" class="stroke-papel" stroke-width="1.2"/>
                        <rect x="-4" y="3.5" width="8" height="5.6" class="fill-senal stroke-papel" stroke-width="0.8"/>
                        <circle cx="0" cy="20" r="2.4" class="fill-asfalto stroke-papel" stroke-width="1.2"/>
                    </g>
                    <g :style="'transform: translateX(' + dibujo.colectivo + 'px); transition: transform 220ms var(--ease-out)'" class="calc-bus">
                        <g transform="translate(0 5.2)">
                            <rect x="-4" y="8.6" width="8" height="4.4" rx="0.8" class="fill-senal stroke-asfalto" stroke-width="0.6"/>
                            <rect x="-2.7" y="9.5" width="1.7" height="2" class="fill-asfalto"/>
                            <rect x="-0.3" y="9.5" width="1.7" height="2" class="fill-asfalto"/>
                            <rect x="1.8" y="9.5" width="1.4" height="2" class="fill-asfalto"/>
                            <circle cx="-2" cy="13" r="0.8" class="fill-asfalto"/>
                            <circle cx="2" cy="13" r="0.8" class="fill-asfalto"/>
                        </g>
                    </g>
                </svg>
                <p class="mt-1 flex justify-between text-sm text-papel/70" aria-hidden="true"><span>Colectivo</span><span>Tu parada</span></p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <label class="block">
                        <span class="flex items-baseline justify-between font-semibold"><span>Le faltan</span><span class="font-panel text-2xl font-bold text-senal" x-text="distancia + ' m'"></span></span>
                        <input type="range" class="linea-de-tiempo mt-1" min="100" max="3600" step="50" x-model.number="distancia" aria-label="Metros que le faltan al colectivo">
                    </label>
                    <label class="block">
                        <span class="flex items-baseline justify-between font-semibold"><span>Viene a</span><span class="font-panel text-2xl font-bold text-senal" x-text="velocidadKmh + ' km/h'"></span></span>
                        <input type="range" class="linea-de-tiempo mt-1" min="8" max="40" step="1" x-model.number="velocidadKmh" aria-label="Velocidad media del colectivo en kilómetros por hora">
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-3" role="group" aria-label="Qué le pasa al colectivo">
                    <button type="button" @click="desvio = !desvio" :aria-pressed="desvio"
                            class="flex items-center gap-2 rounded-full border-2 px-4 py-2 font-semibold"
                            :class="desvio ? 'border-senal bg-senal text-asfalto' : 'hover:bg-papel hover:text-asfalto'" :style="desvio ? '' : 'border-color: color-mix(in srgb, var(--papel) 45%, transparent)'">
                        <x-icono nombre="desvio" :tamano="20" /> Hace un desvío
                    </button>
                    <button type="button" @click="falla = !falla" :aria-pressed="falla"
                            class="flex items-center gap-2 rounded-full border-2 px-4 py-2 font-semibold"
                            :class="falla ? 'border-senal bg-senal text-asfalto' : 'hover:bg-papel hover:text-asfalto'" :style="falla ? '' : 'border-color: color-mix(in srgb, var(--papel) 45%, transparent)'">
                        <x-icono nombre="incidente" :tamano="20" /> Se rompió
                    </button>
                </div>

                {{-- De dónde sale cada segundo --}}
                <div class="mt-6">
                    <div class="flex h-9 w-full overflow-hidden rounded-sm border-2" style="border-color: color-mix(in srgb, var(--papel) 40%, transparent)" role="img"
                         :aria-label="'Del total, ' + tramos.map(t => t.texto + ' ' + segundosTexto(t.segundos)).join(', ')">
                        <template x-for="t in tramos" :key="t.clave">
                            <div class="h-full transition-[width] duration-200" :style="'width:' + t.ancho + '%'" :class="'tramo-' + t.clave"></div>
                        </template>
                    </div>
                    <ul class="mt-3 grid gap-x-6 gap-y-1.5 text-[0.97rem] sm:grid-cols-2">
                        <template x-for="t in tramos" :key="t.clave">
                            <li class="flex items-center gap-2"><span class="h-4 w-6 shrink-0 rounded-[2px] border" style="border-color: color-mix(in srgb, var(--papel) 40%, transparent)" :class="'tramo-' + t.clave"></span><span x-text="t.texto"></span><span class="ml-auto font-semibold" x-text="segundosTexto(t.segundos)"></span></li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- El rótulo luminoso con el resultado --}}
            <div class="flex flex-col justify-between gap-5 border-t-[3px] border-senal p-5 sm:p-8 lg:border-l-[8px] lg:border-t-0 lg:border-double">
                <div>
                    <p class="flex items-center gap-2 font-semibold text-papel/80"><x-icono nombre="parada" :tamano="22" /> Llega en</p>
                    <p class="mt-2 font-panel text-[clamp(3.4rem,8vw,5.6rem)] font-bold leading-none text-senal" aria-live="polite" x-text="rotulo"></p>
                    <p x-show="falla" x-cloak class="mt-3 flex items-start gap-2 rounded-sm border-2 border-dashed border-senal p-2.5 text-sm"><x-icono nombre="incidente" :tamano="20" class="mt-0.5 shrink-0" /> Con una falla no se sabe cuándo arreglan: se suma un tiempo típico de {{ round($est['arreglo_s'] / 60) }} minutos y la cuenta queda marcada como incierta.</p>
                </div>
                <dl class="space-y-1.5 text-sm text-papel/80">
                    <div class="flex justify-between gap-3"><dt>Cada parada del camino</dt><dd class="font-semibold text-papel">{{ str_replace('.', ',', $est['parada_s']) }} s</dd></div>
                    <div class="flex justify-between gap-3"><dt>Velocidad mínima que usa</dt><dd class="font-semibold text-papel">{{ str_replace('.', ',', round($est['velocidad_minima_ms'] * 3.6, 1)) }} km/h</dd></div>
                    <div class="flex justify-between gap-3"><dt>Por un desvío anda a</dt><dd class="font-semibold text-papel">{{ str_replace('.', ',', $est['factor_desvio']) }} de su velocidad</dd></div>
                </dl>
            </div>
        </div>

        <div class="mt-5 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['en-hora', 'Se mide contra el simulador', 'El simulador sabe cuándo llega cada colectivo de verdad. Comparando, el error mediano de la estimación es de 6,4 %.'],
                ['colectivo', 'No usa la velocidad del cartel', 'Usa una media de lo que viene andando. Si viene demorado, la cuenta ya lo refleja.'],
                ['parada', 'Casos que se resuelven aparte', 'Recién pasó, viene por el ramal contrario, espera en la terminal o es el último del día: cada uno tiene su cuenta.'],
            ] as $i => [$icono, $titulo, $texto])
                <div class="entra-campo border-[3px] border-texto p-4" style="--i: {{ $i }}">
                    <x-icono :nombre="$icono" :tamano="28" />
                    <p class="mt-2 font-titulo text-2xl font-extrabold leading-none">{{ $titulo }}</p>
                    <p class="mt-1.5 text-[0.97rem] text-apoyo">{{ $texto }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===================== TIEMPO REAL ===================== --}}
    <section id="tiempo-real" class="mt-20" aria-labelledby="titulo-tiempo">
        <div class="flex items-center gap-3">
            <span class="cartel-parada flex h-12 w-12 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="en-vivo" :tamano="28" /></span>
            <h2 id="titulo-tiempo" class="font-titulo text-[clamp(2.4rem,5vw,3.6rem)] font-black leading-[0.9]">De la base de datos al mapa</h2>
        </div>
        <p class="mt-3 max-w-[64ch] text-lg">Cada posición hace este viaje, cada {{ rtrim(rtrim(number_format($cifras['intervalo_s'], 1, ',', ''), '0'), ',') }} segundos, para los {{ $cifras['colectivos'] }} colectivos. Cortá el WebSocket para ver el plan B.</p>

        <div x-data="recorridoDelDato" class="tema-noche tablero mt-8 overflow-hidden rounded-sm p-5 sm:p-8">
            <ol class="grid gap-0 lg:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr_auto_1fr] lg:items-stretch" aria-label="Recorrido de una posición">
                @foreach ([
                    ['reloj', 'Simulador', 'Mueve a los colectivos y calcula dónde está cada uno.'],
                    ['mapa', 'Base de datos', 'Guarda la posición y, cada '.$hist['paso_s'].' s, una foto para el historial.'],
                    ['filtro', 'Cola', 'Prepara el mensaje aparte, sin frenar al simulador.'],
                    ['en-vivo', 'Reverb', 'Servidor de WebSockets: lo manda solo a quien mira esa zona.'],
                    ['colectivo', 'Tu navegador', 'Desliza cada colectivo entre una posición y la siguiente.'],
                ] as $i => [$icono, $titulo, $texto])
                    <li class="flex flex-col gap-1.5 rounded-sm border-2 p-4" style="border-color: color-mix(in srgb, var(--papel) 35%, transparent)"
                        :class="paso === {{ $i }} ? 'bg-senal text-asfalto' : ''">
                        <button type="button" @click="elegir({{ $i }})" :aria-pressed="paso === {{ $i }}" class="flex items-center gap-2 text-left font-titulo text-[1.7rem] font-extrabold leading-none">
                            <x-icono :nombre="$icono" :tamano="26" /> {{ $titulo }}
                        </button>
                        <p class="text-[0.95rem]" :class="paso === {{ $i }} ? 'text-asfalto' : 'text-papel/75'">{{ $texto }}</p>
                    </li>
                    @if (! $loop->last)
                        <li class="conector" aria-hidden="true" @if ($i === 3) :class="websocket ? '' : 'cortado'" @endif>
                            <span class="conector-linea"></span>
                            <span class="paquete" style="--d: {{ $i * 0.35 }}s"></span>
                            <span class="paquete" style="--d: {{ $i * 0.35 + 0.8 }}s"></span>
                            @if ($i === 3)
                                <span x-show="!websocket" x-cloak class="conector-corte"><x-icono nombre="sin-senal" :tamano="26" /></span>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ol>

            {{-- Plan B --}}
            <div x-show="!websocket" x-cloak x-transition:enter="transition duration-200 ease-out motion-reduce:duration-75" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-4 flex flex-wrap items-center gap-3 rounded-sm border-2 border-dashed border-senal p-4" role="status">
                <x-icono nombre="reloj" :tamano="28" class="shrink-0 text-senal" />
                <p class="min-w-0 flex-1"><span class="font-semibold text-senal">Plan B:</span> el navegador deja de esperar y le pregunta a <span class="codigo">/api/posiciones</span> cada 2 segundos. Se ve igual, con un poco más de demora. Cuando el WebSocket vuelve, retoma solo.</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" @click="alternar()" :aria-pressed="!websocket" class="cartel-parada flex items-center gap-2 rounded-sm px-5 py-2.5 font-titulo text-xl font-extrabold leading-none">
                    <span x-show="websocket" class="flex items-center gap-2"><x-icono nombre="sin-senal" :tamano="22" /> Cortar el WebSocket</span>
                    <span x-show="!websocket" x-cloak class="flex items-center gap-2"><x-icono nombre="en-vivo" :tamano="22" /> Reconectar</span>
                </button>
                <p class="text-sm text-papel/70">La demo publicada usa el plan B: el hosting gratuito se duerme y no sostiene conexiones abiertas.</p>
            </div>
        </div>

        {{-- Las celdas: el servidor manda solo lo que se ve --}}
        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_1fr] lg:items-center">
            <div>
                <h3 class="font-titulo text-[2.2rem] font-black leading-none">El servidor manda solo lo que mirás</h3>
                <p class="mt-3 text-lg">La ciudad se divide en {{ $grilla['columnas'] * $grilla['filas'] }} celdas, cada una con su propio canal. El mapa se suscribe únicamente a las que tiene en pantalla, así que al acercarte recibís menos datos.</p>
                <p class="mt-3 text-apoyo">Tocá las celdas para elegir cuáles estás mirando.</p>
            </div>

            <div x-data="grillaDeCanales({{ $grilla['columnas'] }}, {{ $grilla['filas'] }})" class="border-[3px] border-texto p-4 sm:p-5">
                <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $grilla['columnas'] }}, minmax(0, 1fr))" role="group" aria-label="Celdas de la ciudad">
                    @for ($i = 0; $i < $grilla['columnas'] * $grilla['filas']; $i++)
                        <button type="button" @click="alternar({{ $i }})" :aria-pressed="esActiva({{ $i }})" aria-label="Celda {{ $i + 1 }}"
                                class="celda-canal flex aspect-[4/3] items-center justify-center rounded-[3px] border-2 border-texto"
                                :class="esActiva({{ $i }}) ? 'bg-senal text-asfalto' : 'bg-superficie text-apoyo'">
                            <span x-show="esActiva({{ $i }})" x-cloak><x-icono nombre="en-vivo" :tamano="22" /></span>
                            <span x-show="!esActiva({{ $i }})" class="text-sm font-semibold">{{ $i + 1 }}</span>
                        </button>
                    @endfor
                </div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold" role="status">Escuchás <span class="font-titulo text-3xl font-black" x-text="cantidad"></span> de {{ $grilla['columnas'] * $grilla['filas'] }} canales</p>
                    <div class="flex gap-2" role="group" aria-label="Vistas de ejemplo">
                        <button type="button" @click="vista('toda')" class="rounded-full border-2 border-texto px-3 py-1 text-sm font-semibold hover:bg-texto hover:text-fondo">Toda la ciudad</button>
                        <button type="button" @click="vista('centro')" class="rounded-full border-2 border-texto px-3 py-1 text-sm font-semibold hover:bg-texto hover:text-fondo">El centro</button>
                        <button type="button" @click="vista('una')" class="rounded-full border-2 border-texto px-3 py-1 text-sm font-semibold hover:bg-texto hover:text-fondo">Una cuadra</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== HISTORIAL ===================== --}}
    <section class="mt-20" aria-labelledby="titulo-historial">
        <div class="flex items-center gap-3">
            <span class="cartel-parada flex h-12 w-12 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="rebobinar" :tamano="28" /></span>
            <h2 id="titulo-historial" class="font-titulo text-[clamp(2.4rem,5vw,3.6rem)] font-black leading-[0.9]">Cómo se rebobina el día</h2>
        </div>
        <p class="mt-3 max-w-[64ch] text-lg">Cada {{ $hist['paso_s'] }} segundos se guarda una foto de dónde estaba cada colectivo. Al arrastrar la línea de tiempo, el navegador calcula las posiciones entre dos fotos, y los colectivos se mueven de forma continua hacia atrás y hacia adelante.</p>

        <div class="tema-noche tablero mt-8 overflow-hidden rounded-sm p-5 sm:p-8">
            <p class="font-semibold text-papel/80">Así se guarda un colectivo en una foto: {{ $hist['bytes_por_colectivo'] }} bytes</p>
            <ol class="mt-3 flex w-full gap-1" aria-label="Campos de un colectivo en una foto">
                @foreach ($campos as $i => [$clave, $nombre, $bytes])
                    <li class="min-w-0 {{ $i % 2 === 0 ? 'bg-senal text-asfalto' : 'border-2 border-senal text-senal' }} rounded-[3px] px-1 py-3 text-center" style="flex: {{ $bytes }} 1 0">
                        <p class="font-panel text-[1.4rem] font-bold leading-none sm:text-[2rem]">{{ $bytes }} B</p>
                        <p class="mt-1 truncate text-[0.7rem] font-semibold sm:text-sm">{{ $nombre }}</p>
                    </li>
                @endforeach
            </ol>

            <dl class="mt-6 grid grid-cols-2 gap-px border-2 border-dashed lg:grid-cols-4" style="border-color: color-mix(in srgb, var(--papel) 25%, transparent)">
                @foreach ([
                    [$hist['bytes_por_foto'].' B', 'por foto con '.$cifras['colectivos'].' colectivos'],
                    [$hist['paso_s'].' s', 'entre una foto y la siguiente'],
                    [str_replace('.', ',', $hist['mb_retenidos']).' MB', 'en '.$hist['retencion_horas'].' horas de historial'],
                    [$hist['retencion_horas'].' h', 'y después se borra solo'],
                ] as [$valor, $texto])
                    <div class="p-4">
                        <dd class="font-panel text-[clamp(2rem,4vw,3rem)] font-bold leading-none text-senal">{{ $valor }}</dd>
                        <dt class="mt-1.5 text-sm text-papel/75">{{ $texto }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
        <p class="mt-4 max-w-[64ch] text-apoyo">Si un colectivo cambia de ramal o aparece a más de 400 metros de donde estaba, se lo corta ahí en vez de hacerlo cruzar la ciudad. Los huecos sin fotos no se rellenan: se avisa que no hay registro.</p>
    </section>

    {{-- ===================== LÍMITES ===================== --}}
    <section class="mt-20" aria-labelledby="titulo-limites">
        <h2 id="titulo-limites" class="font-titulo text-[clamp(2.4rem,5vw,3.6rem)] font-black leading-[0.9]">Lo que conviene saber</h2>
        <div class="calzada-doble mt-4" aria-hidden="true"></div>
        <ul class="mt-6 grid gap-x-10 gap-y-5 md:grid-cols-2">
            @foreach ([
                ['incidente', 'Los desvíos usan perfil de auto', 'Se calcularon con OSRM, que piensa en autos. Algunas calles que usan los desvíos quizás un colectivo real no podría tomarlas.'],
                ['reloj', 'La demo publicada duerme', 'El hosting gratuito apaga el servicio tras unos minutos sin visitas, por eso el primer ingreso puede tardar. El WebSocket completo se probó en local.'],
                ['api', 'El feed GTFS está a medias', 'Las posiciones salen en GTFS Realtime, pero falta el GTFS estático (paradas y rutas) para que otras aplicaciones lo crucen con horarios.'],
                ['en-hora', 'Mide contra su propio simulador', 'El 6,4 % de error compara la estimación con el simulador, que conoce la verdad. Con colectivos reales habría que volver a medir.'],
            ] as [$icono, $titulo, $texto])
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-texto text-fondo"><x-icono :nombre="$icono" :tamano="24" /></span>
                    <div><p class="font-titulo text-2xl font-extrabold leading-none">{{ $titulo }}</p><p class="mt-1 text-apoyo">{{ $texto }}</p></div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ===================== SEGUIR ===================== --}}
    <section class="mt-20" aria-label="Seguir explorando">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                [route('mapa'), 'mapa', 'Ver el mapa en vivo', 'Los '.$cifras['colectivos'].' colectivos moviéndose ahora.'],
                [route('api'), 'api', 'Probar la API', 'Pedí posiciones, paradas y llegadas con un token.'],
                [route('lineas'), 'linea', 'Mirar las líneas', 'Los recorridos sobre las calles de Paraná.'],
            ] as [$enlace, $icono, $titulo, $texto])
                <a href="{{ $enlace }}" class="cartel-parada group flex items-center gap-4 rounded-sm p-5">
                    <x-icono :nombre="$icono" :tamano="36" />
                    <div><p class="font-titulo text-[1.9rem] font-black leading-none">{{ $titulo }}</p><p class="mt-1 text-[0.97rem]">{{ $texto }}</p></div>
                </a>
            @endforeach
        </div>
    </section>
</main>

@include('partials.pie')
@endsection
