@extends('layouts.base')

@section('titulo', 'Líneas de Ramal')
@section('descripcion', 'Las cinco líneas de colectivos de Paraná, con sus paradas y recorridos sobre calles reales.')

@section('cuerpo')
@include('partials.cabecera', ['enlaces' => ['plano' => 'Plano', 'detalle' => 'Detalle']])

<main id="contenido" class="mx-auto max-w-7xl px-4 pb-16 pt-6 sm:px-8">
    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr] lg:items-end">
        <h1 class="font-titulo text-[clamp(3.2rem,9vw,7.5rem)] font-black leading-[0.88]">Cinco líneas, calles reales</h1>
        <p class="max-w-[46ch] text-lg text-apoyo lg:pb-3">
            Las líneas son inventadas. Los recorridos no: se calcularon una sola vez sobre las calles de Paraná, con sus manos y sus giros prohibidos, y quedaron guardados en la base de datos.
        </p>
    </div>

    {{-- Las cifras de la red, como un rótulo luminoso --}}
    <dl class="tema-noche tablero mt-8 grid grid-cols-2 overflow-hidden rounded-sm lg:grid-cols-4">
        @foreach ([['Líneas', $cifras['lineas'], 'linea'], ['Paradas', $cifras['paradas'], 'parada'], ['Kilómetros de recorrido', $cifras['kilometros'], 'ramal'], ['Colectivos', $cifras['colectivos'], 'colectivo']] as $i => [$titulo, $valor, $icono])
            <div class="entra-campo flex flex-col gap-2 border-papel/20 px-5 py-4 {{ $i > 0 ? 'lg:border-l-2 lg:border-dashed' : '' }}" style="--i: {{ $i }}">
                <dt class="flex items-center gap-1.5 text-sm font-semibold text-papel/75"><x-icono :nombre="$icono" :tamano="18" /> {{ $titulo }}</dt>
                <dd class="font-panel text-[clamp(2.8rem,4.4vw,4rem)] font-bold leading-none text-senal">{{ $valor }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- ===================== PLANO ===================== --}}
    <section id="plano" class="mt-10" aria-labelledby="titulo-plano">
        <h2 id="titulo-plano" class="sr-only">Mapa de los recorridos</h2>
        <div class="overflow-hidden rounded-md border-[3px] border-texto" style="background: var(--mapa-fondo)">
            <svg viewBox="0 0 {{ $mapa['ancho'] }} {{ $mapa['alto'] }}" class="block h-auto max-h-[82vh] w-full" role="img"
                 aria-label="Recorridos de las cinco líneas sobre las calles de Paraná. La ida va con trazo continuo y la vuelta con trazo discontinuo.">
                <rect width="{{ $mapa['ancho'] }}" height="{{ $mapa['alto'] }}" style="fill: var(--mapa-fondo)"/>

                @foreach ($mapa['trazos'] as $trazo)
                    <path d="{{ $trazo['d'] }}" fill="none" stroke-linejoin="round" stroke-linecap="round"
                          style="stroke: var(--linea-{{ $trazo['linea'] }}); stroke-width: 5; opacity: {{ $trazo['sentido'] === 'ida' ? '.9' : '.7' }}"
                          @if ($trazo['sentido'] === 'vuelta') stroke-dasharray="2 9" @endif/>
                @endforeach

                @foreach ($mapa['paradas'] as $parada)
                    <g transform="translate({{ $parada['x'] }} {{ $parada['y'] }})">
                        <path d="M0 0V-24" style="stroke: var(--texto); stroke-width: 3; stroke-linecap: square"/>
                        <rect x="-11" y="-38" width="22" height="16" style="fill: var(--senal); stroke: var(--texto); stroke-width: 2.5"/>
                        <circle r="4.5" style="fill: var(--fondo); stroke: var(--texto); stroke-width: 2.5"/>
                        <text x="{{ $parada['x'] > $mapa['ancho'] * 0.72 ? -16 : 16 }}" y="-26" text-anchor="{{ $parada['x'] > $mapa['ancho'] * 0.72 ? 'end' : 'start' }}" style="fill: var(--texto); font: 700 15px var(--font-sans); paint-order: stroke; stroke: var(--mapa-fondo); stroke-width: 4px; stroke-linejoin: round">{{ $parada['nombre'] }}</text>
                    </g>
                @endforeach
            </svg>
        </div>
        <p class="mt-3 max-w-[70ch] text-sm text-apoyo">
            Trazo continuo: ida. Trazo de puntos: vuelta. Donde ida y vuelta no coinciden, es porque hay calles de una sola mano.
            Mapa dibujado con datos de OpenStreetMap (ODbL) y recorridos calculados con OSRM.
        </p>
    </section>

    <x-colectivos-linea class="mx-auto my-12 max-w-5xl text-apoyo opacity-80" />

    {{-- ===================== DETALLE POR LÍNEA ===================== --}}
    <section id="detalle" aria-labelledby="titulo-detalle">
        <h2 id="titulo-detalle" class="font-titulo text-[clamp(2.4rem,5vw,4rem)] font-black leading-[0.9]">Plano de recorridos</h2>
        <p class="mt-3 max-w-[60ch] text-apoyo">La distancia entre círculos sigue la distancia real por la calle, en la ida.</p>

        <ol class="mt-8 space-y-12">
            @foreach ($lineas as $linea)
                @php
                    $ida = $linea->ramales->firstWhere('sentido', 'ida');
                    $vuelta = $linea->ramales->firstWhere('sentido', 'vuelta');
                    $habil = $linea->horarios->firstWhere('dia', 'habil');
                    $largo = max(1, $ida->recorrido->largo_m);
                @endphp
                <li class="grid items-start gap-4 sm:grid-cols-[9rem_1fr]">
                    <div class="flex items-center gap-3 sm:block">
                        <span class="flex h-16 w-16 items-center justify-center rounded-sm font-titulo text-[2.8rem] font-black leading-none"
                              style="background: var(--linea-{{ $linea->numero }}); color: var(--sobre-linea)">{{ $linea->numero }}</span>
                        <div class="sm:mt-3">
                            <p class="font-titulo text-[1.5rem] font-extrabold leading-none">{{ $linea->destino }}</p>
                            <p class="mt-1 text-sm text-apoyo">{{ number_format($ida->recorrido->largo_m / 1000, 1, ',', '') }} km de ida</p>
                        </div>
                    </div>

                    <div>
                        <div class="relative ml-3 mr-16 h-20 sm:mr-28" role="img"
                             aria-label="Paradas de la línea {{ $linea->numero }}: {{ $ida->paradas->pluck('nombre')->implode(', ') }}">
                            <div class="absolute left-0 right-0 top-[10px] h-[5px]" style="background: var(--linea-{{ $linea->numero }})"></div>
                            @foreach ($ida->paradas as $parada)
                                <div class="absolute top-0" style="left: {{ round($parada->pivot->distancia_m / $largo * 100, 2) }}%; transform: translateX(-50%)">
                                    <span class="block h-[25px] w-[25px] rounded-full border-[5px] bg-fondo" style="border-color: var(--linea-{{ $linea->numero }})"></span>
                                    <span class="absolute left-1/2 top-8 w-24 -translate-x-1/2 text-center text-[0.8rem] font-semibold leading-tight">{{ $parada->nombre }}</span>
                                </div>
                            @endforeach
                        </div>

                        <dl class="mt-2 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                            <div class="flex items-center gap-2"><dt class="flex items-center gap-1.5 text-apoyo"><x-icono nombre="parada" :tamano="18" /> Paradas</dt><dd class="font-semibold">{{ $ida->paradas->count() }}</dd></div>
                            <div class="flex items-center gap-2"><dt class="flex items-center gap-1.5 text-apoyo"><x-icono nombre="colectivo" :tamano="18" /> Colectivos</dt><dd class="font-semibold">{{ $linea->colectivos_count }}</dd></div>
                            <div class="flex items-center gap-2"><dt class="flex items-center gap-1.5 text-apoyo"><x-icono nombre="reloj" :tamano="18" /> Días hábiles</dt><dd class="font-semibold">de {{ substr($habil->desde, 0, 5) }} a {{ substr($habil->hasta, 0, 5) }}, cada {{ $habil->frecuencia_min }} min</dd></div>
                            <div class="flex items-center gap-2"><dt class="flex items-center gap-1.5 text-apoyo"><x-icono nombre="ramal" :tamano="18" /> Vuelta</dt><dd class="font-semibold">{{ number_format($vuelta->recorrido->largo_m / 1000, 1, ',', '') }} km</dd></div>
                        </dl>
                    </div>
                </li>
                @if (! $loop->last)
                    <li aria-hidden="true" class="calzada-discontinua list-none text-texto"></li>
                @endif
            @endforeach
        </ol>
    </section>
</main>

@include('partials.pie')
@endsection
