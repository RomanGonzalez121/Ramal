@extends('layouts.base')

@section('titulo', 'Identidad de Ramal')
@section('descripcion', 'Logo, colores en los dos temas, tipografías, íconos y piezas propias de Ramal, con una demostración en vivo del movimiento.')

@php
    $iconos = [
        'colectivo' => 'Colectivo', 'parada' => 'Parada', 'linea' => 'Línea', 'ramal' => 'Ramal',
        'demora' => 'Demora', 'desvio' => 'Desvío', 'incidente' => 'Incidente', 'fuera-de-recorrido' => 'Fuera de recorrido',
        'reloj' => 'Reloj', 'rebobinar' => 'Rebobinar', 'reproducir' => 'Reproducir', 'pausar' => 'Pausar',
        'velocidad' => 'Velocidad', 'chofer' => 'Chofer', 'operador' => 'Operador', 'filtro' => 'Filtro',
        'alerta' => 'Alerta', 'api' => 'API', 'mapa' => 'Mapa', 'ubicacion' => 'Ubicación',
    ];

    // Plano de recorridos: cada línea con sus paradas (la distancia entre círculos es proporcional al tramo).
    $planos = [
        1 => ['destino' => 'Terminal', 'paradas' => [['Plaza', 0], ['Hospital', 2], ['Escuela', 5], ['Terminal', 8]]],
        2 => ['destino' => 'Costanera', 'paradas' => [['Mercado', 0], ['Estación', 3], ['Club', 4], ['Municipalidad', 7], ['Costanera', 9]]],
        3 => ['destino' => 'Parque', 'paradas' => [['Escuela', 0], ['Club', 4], ['Parque', 6]]],
        4 => ['destino' => 'Barrio Norte', 'paradas' => [['Municipalidad', 0], ['Biblioteca', 2], ['Correo', 3], ['Banco', 6], ['Barrio Norte', 7]]],
        5 => ['destino' => 'Cementerio', 'paradas' => [['Correo', 0], ['Banco', 1], ['Cementerio', 4]]],
    ];
@endphp

@section('cuerpo')
@include('partials.cabecera', ['enlaces' => ['mapa' => 'Mapa', 'colores' => 'Colores', 'tipografia' => 'Tipografía', 'iconos' => 'Íconos', 'piezas' => 'Piezas', 'logo' => 'Logo']])

<main id="contenido">

{{-- ===================== MAPA EN VIVO ===================== --}}
<section id="mapa" x-data="demoMapa" class="mx-auto max-w-7xl px-4 pb-14 pt-6 sm:px-8">
    <div class="grid gap-6 lg:grid-cols-[1.7fr_1fr] lg:items-end">
        <h1 class="font-titulo text-[clamp(3.2rem,9vw,7.5rem)] font-black leading-[0.88]">
            Colectivos de Paraná, en vivo
        </h1>
        <p class="max-w-[46ch] text-lg text-apoyo lg:pb-3">
            Las calles son reales. Los cuarenta colectivos, sus choferes y sus horarios los inventa un simulador,
            y acá se mueven como lo harán en el mapa final. Tocá una parada para ver cuándo llega el próximo.
        </p>
    </div>

    <div class="relative mt-8 overflow-hidden rounded-md border-[3px] border-texto">
        <svg x-ref="mapa" viewBox="0 0 1200 700" preserveAspectRatio="xMidYMid slice"
             class="block h-[clamp(430px,70vh,740px)] w-full touch-manipulation" role="img"
             aria-label="Mapa esquemático con cinco líneas de colectivos en movimiento"></svg>

        {{-- Panel de la parada elegida: cartel + rótulo luminoso --}}
        <div class="pointer-events-none relative z-10 p-0 md:absolute md:bottom-4 md:left-4 md:w-[23rem] md:p-0">
            <div class="pointer-events-auto flex items-stretch gap-4 bg-fondo p-4 md:border-[3px] md:border-texto">
                <div class="flex flex-col items-center" aria-hidden="true">
                    <div class="cartel-parada flex h-14 w-14 items-center justify-center rounded-sm">
                        <x-icono nombre="colectivo" :tamano="32" />
                    </div>
                    <div class="mt-0 w-[5px] flex-1 bg-asfalto" style="background: var(--texto)"></div>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate font-titulo text-[1.7rem] font-extrabold leading-none" x-text="paradaNombre">Club</p>
                    <p class="mt-1 text-sm text-apoyo">Próximo colectivo de la línea <span x-text="paradaLinea">3</span></p>

                    <div class="rotulo mt-3 flex items-center gap-3 rounded-sm px-3 py-2.5" role="status" aria-live="polite">
                        <span class="flex h-12 min-w-12 items-center justify-center rounded-sm px-2 font-titulo text-[2.1rem] font-black leading-none"
                              :style="'background:var(--linea-' + paradaLinea + ');color:var(--sobre-linea)'" x-text="paradaLinea">3</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-panel text-sm font-bold uppercase leading-none" x-text="destino">Parque</p>
                            <p class="rotulo-digitos mt-1.5 leading-none"
                               :class="[parpadeo && 'parpadeo-panel', llegando ? 'text-[1.9rem]' : 'text-[2.7rem]']"
                               @animationend="parpadeo = false">
                                <span x-text="minutos">04</span><span x-show="!llegando" class="ml-2 text-lg font-bold">min</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-4">
        <div class="flex items-center gap-2" role="group" aria-label="Elegir línea">
            <span class="mr-1 font-semibold">Líneas</span>
            <template x-for="n in lineas" :key="n">
                <button type="button" @click="elegirLinea(n)" :aria-pressed="linea === n"
                        class="flex h-11 w-11 items-center justify-center rounded-sm font-titulo text-[1.6rem] font-black leading-none transition-transform duration-100 active:scale-95"
                        :class="linea === n ? 'outline outline-[3px] outline-offset-2 outline-texto' : ''"
                        :style="'background:var(--linea-' + n + ');color:var(--sobre-linea)'" x-text="n"></button>
            </template>
            <button type="button" x-show="linea !== null" @click="elegirLinea(linea)"
                    class="ml-1 rounded-full border-2 border-texto px-3 py-1.5 text-sm font-semibold hover:bg-texto hover:text-fondo">Ver todas</button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="cortar()" :disabled="cortado"
                    class="flex items-center gap-2 rounded-full border-2 border-texto px-4 py-2 text-[0.95rem] font-semibold transition-colors duration-100 hover:bg-texto hover:text-fondo disabled:opacity-50 disabled:hover:bg-transparent disabled:hover:text-texto">
                <x-icono nombre="alerta" :tamano="20" />
                <span x-text="cortado ? 'Sin datos del servidor' : 'Cortar los datos 6 s'">Cortar los datos 6 s</span>
            </button>
            <p class="text-sm text-apoyo" aria-live="off">
                <span class="font-semibold text-texto" x-text="fps">60</span> cuadros por segundo,
                <span x-text="ms">16.7</span> ms por cuadro
            </p>
        </div>
    </div>
    <p class="mt-3 max-w-[70ch] text-sm text-apoyo">
        Al cortar los datos los colectivos quedan donde están. Cuando vuelven, el servidor informa recorridos largos y el
        navegador los alcanza sin saltos ni teletransportes.
    </p>
</section>

<x-colectivos-linea class="mx-auto max-w-5xl px-4 text-apoyo opacity-80" />

{{-- ===================== COLORES ===================== --}}
<section id="colores" class="mx-auto max-w-7xl px-4 py-16 sm:px-8">
    <div class="calzada-doble mb-12" aria-hidden="true"></div>

    <div class="grid gap-10 lg:grid-cols-[minmax(0,26rem)_1fr]">
        <div>
            <h2 class="font-titulo text-[clamp(2.8rem,6vw,4.8rem)] font-black leading-[0.9]">Dos temas, un plano</h2>
            <div class="mt-5 space-y-4 text-[1.05rem]">
                <p>Día es el plano en papel. Noche es el mapa oscuro, como el rótulo luminoso sobre el parabrisas.</p>
                <p>Cada línea tiene un color y ese color es su identidad en todo el sitio: recorrido, colectivo, número y gráfico. No se usa para otra cosa.</p>
                <p>El estado nunca se dice solo con color. Siempre lleva ícono y palabra.</p>
                <p>El amarillo Señal sobre Papel da <strong>{{ $senalSobrePapel }}</strong>: nunca va como texto ni figura sobre Papel. Con Asfalto encima da <strong>{{ $senalSobreAsfalto }}</strong>, como el cartel de la parada.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] border-collapse text-left">
                <caption class="sr-only">Colores de Ramal con su valor en Día y en Noche y el contraste contra el fondo del tema</caption>
                <thead>
                    <tr class="border-b-[3px] border-texto text-sm">
                        <th scope="col" class="pb-2 pr-3 font-semibold">Color</th>
                        <th scope="col" class="pb-2 pr-3 font-semibold">Día, sobre Papel</th>
                        <th scope="col" class="pb-2 pr-3 font-semibold">Noche, sobre Asfalto</th>
                        <th scope="col" class="pb-2 font-semibold">Uso</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($colores as $color)
                    <tr class="border-b border-dashed border-apoyo/50 align-middle">
                        <th scope="row" class="py-3 pr-3 font-titulo text-[1.5rem] font-extrabold leading-none">{{ $color['nombre'] }}</th>
                        <td class="py-3 pr-3">
                            @if ($color['dia'])
                                <div class="flex items-center gap-2.5">
                                    <span class="h-9 w-14 shrink-0 rounded-sm border-2 border-asfalto" style="background: {{ $color['dia'] }}"></span>
                                    <span class="text-sm leading-tight"><span class="font-semibold">{{ $color['dia'] }}</span><br><span class="text-apoyo">{{ $color['ratio_dia'] ?? 'Superficie' }}</span></span>
                                </div>
                            @else
                                <span class="text-apoyo">No cambia</span>
                            @endif
                        </td>
                        <td class="py-3 pr-3">
                            @if ($color['noche'])
                                <div class="flex items-center gap-2.5">
                                    <span class="h-9 w-14 shrink-0 rounded-sm border-2 border-papel" style="background: {{ $color['noche'] }}"></span>
                                    <span class="text-sm leading-tight"><span class="font-semibold">{{ $color['noche'] }}</span><br><span class="text-apoyo">{{ $color['ratio_noche'] ?? 'Superficie' }}</span></span>
                                </div>
                            @else
                                <span class="text-apoyo">No existe</span>
                            @endif
                        </td>
                        <td class="py-3 text-sm">{{ $color['uso'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="mt-3 max-w-[60ch] text-sm text-apoyo">Los contrastes se calculan en el servidor con la fórmula de WCAG y un test los vigila. El Bordó de Noche baja a 4,9:1 sobre Calzada: no se usa más chico que 14 px.</p>
        </div>
    </div>
</section>

{{-- ===================== TIPOGRAFÍA ===================== --}}
<section id="tipografia" class="mx-auto max-w-7xl px-4 py-16 sm:px-8">
    <div class="calzada-doble mb-12" aria-hidden="true"></div>
    <h2 class="font-titulo text-[clamp(2.8rem,6vw,4.8rem)] font-black leading-[0.9]">Tres voces</h2>

    <div class="mt-10 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <p class="font-titulo text-[clamp(4rem,12vw,9.5rem)] font-black leading-[0.82]">Terminal Costanera</p>
            <p class="mt-4 font-titulo text-[2rem] font-bold leading-none">700 &nbsp; <span class="font-extrabold">800</span> &nbsp; <span class="font-black">900</span></p>
            <p class="mt-4 max-w-[52ch]">
                <strong>Big Shoulders Display.</strong> Condensada, viene de la rotulación de transporte. Para los números de línea, los destinos y los titulares grandes.
            </p>
        </div>

        <div class="lg:col-span-5 lg:pt-10">
            <p class="max-w-[34ch] text-[1.6rem] leading-snug">
                El 3 sale de la Terminal cada doce minutos. Si se demora, el rótulo te avisa antes de que llegues a la esquina.
            </p>
            <p class="mt-4 text-[1.05rem] font-bold">Pesos 400, 500, 600 y 700</p>
            <p class="mt-4 max-w-[48ch]">
                <strong>Familjen Grotesk.</strong> Toda la interfaz y el texto corrido. Se lee rápido y de lejos, hasta en un celular al sol.
            </p>
        </div>

        <div class="lg:col-span-12">
            <div class="rotulo flex flex-wrap items-end gap-x-10 gap-y-4 rounded-sm p-6 sm:p-8">
                <p class="rotulo-digitos text-[clamp(4.5rem,13vw,9rem)]">04<span class="ml-3 text-[0.4em]">min</span></p>
                <p class="rotulo-digitos pb-2 text-[clamp(1.6rem,4vw,3rem)]">Llegando</p>
                <p class="max-w-[40ch] pb-2 font-sans text-base leading-snug text-papel">
                    <strong>Doto.</strong> Puntos, como un panel luminoso. Solo para la cuenta regresiva y el destino. No se usa para nada más.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ===================== ÍCONOS ===================== --}}
<section id="iconos" class="mx-auto max-w-7xl px-4 py-16 sm:px-8">
    <div class="calzada-doble mb-12" aria-hidden="true"></div>

    <div class="grid gap-10 lg:grid-cols-[22rem_1fr]">
        <div>
            <h2 class="font-titulo text-[clamp(2.8rem,6vw,4.8rem)] font-black leading-[0.9]">Veinte íconos propios</h2>
            <p class="mt-5 max-w-[40ch]">Dibujados sobre una grilla de 24 px. Trazo de 2 px con terminaciones cuadradas y círculos huecos como paradas.</p>

            <div class="relative mt-8 inline-block bg-superficie p-5" aria-hidden="true">
                <div class="relative">
                    <div class="absolute inset-0" style="background-image: linear-gradient(var(--apoyo) 1px, transparent 1px), linear-gradient(90deg, var(--apoyo) 1px, transparent 1px); background-size: 12px 12px; opacity: .35"></div>
                    <svg width="288" height="288" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" class="relative"><use href="#i-colectivo"/></svg>
                    <div class="absolute inset-0 border-2 border-dashed border-apoyo"></div>
                </div>
            </div>
        </div>

        <ul class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($iconos as $clave => $nombre)
                <li class="flex items-center gap-3">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center border-2 border-dashed border-apoyo/70">
                        <x-icono :nombre="$clave" :tamano="32" />
                    </span>
                    <span class="font-semibold leading-tight">{{ $nombre }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-12">
        <h3 class="font-titulo text-[2rem] font-extrabold leading-none">Estados</h3>
        <p class="mt-2 max-w-[60ch] text-apoyo">Cada estado se distingue por ícono, palabra y forma del borde. El color no hace falta.</p>
        <ul class="mt-5 flex flex-wrap gap-3">
            <li class="flex items-center gap-2 rounded-full border-2 border-texto px-4 py-2 font-semibold"><x-icono nombre="en-hora" :tamano="22" /> En hora</li>
            <li class="flex items-center gap-2 rounded-full border-2 border-dashed border-texto px-4 py-2 font-semibold"><x-icono nombre="demora" :tamano="22" /> Demorado</li>
            <li class="flex items-center gap-2 rounded-full border-4 border-double border-texto px-4 py-1.5 font-semibold"><x-icono nombre="fuera-de-recorrido" :tamano="22" /> Fuera de recorrido</li>
            <li class="flex items-center gap-2 rounded-full border-2 border-texto bg-texto px-4 py-2 font-semibold text-fondo"><x-icono nombre="incidente" :tamano="22" /> Incidente</li>
        </ul>
    </div>
</section>

{{-- ===================== PIEZAS ===================== --}}
<section id="piezas" class="mx-auto max-w-7xl px-4 py-16 sm:px-8">
    <div class="calzada-doble mb-12" aria-hidden="true"></div>
    <h2 class="font-titulo text-[clamp(2.8rem,6vw,4.8rem)] font-black leading-[0.9]">Piezas propias</h2>
    <p class="mt-4 max-w-[56ch]">Cinco elementos que salen de la calle. Si un adorno no dice algo del transporte, no entra.</p>

    {{-- 1. Cartel de parada --}}
    <div class="mt-12 grid items-center gap-8 md:grid-cols-[18rem_1fr]">
        <div class="flex items-end gap-5" aria-hidden="true">
            <div class="flex flex-col items-center">
                <div class="cartel-parada w-44 rounded-sm px-4 py-3 text-center">
                    <x-icono nombre="colectivo" :tamano="44" class="mx-auto" />
                    <p class="mt-1 font-titulo text-[2.1rem] font-black leading-none">Parada</p>
                    <p class="mt-1 text-sm font-semibold leading-tight">Plaza, líneas 1 y 5</p>
                </div>
                <div class="h-28 w-[7px] bg-texto"></div>
            </div>
        </div>
        <div>
            <h3 class="font-titulo text-[2rem] font-extrabold leading-none">El cartel de parada</h3>
            <p class="mt-3 max-w-[56ch]">Marca las paradas en el mapa y encabeza la pantalla de cada una. Amarillo Señal con Asfalto encima. Es la única sombra del sitio, corta y dura, porque es un objeto apoyado en la calle.</p>
        </div>
    </div>

    <div class="calzada-discontinua my-12 text-texto" aria-hidden="true"></div>

    {{-- 2. Rótulo luminoso --}}
    <div class="grid items-center gap-8 md:grid-cols-[1fr_24rem]">
        <div class="md:order-1">
            <h3 class="font-titulo text-[2rem] font-extrabold leading-none">El rótulo luminoso</h3>
            <p class="mt-3 max-w-[56ch]">La cuenta regresiva, con el número de línea y el destino. Cuando cambia un minuto, los dígitos parpadean como un panel, sin fundido. Con menos de 60 segundos dice "Llegando".</p>
        </div>
        <div class="rotulo flex items-center gap-4 rounded-sm p-4 md:order-2" x-data="{ n: 7, marca: 0 }" x-init="setInterval(() => { n = n === 1 ? 7 : n - 1; marca++ }, 2200)">
            <span class="flex h-16 min-w-16 items-center justify-center rounded-sm px-3 font-titulo text-[2.8rem] font-black leading-none" style="background: var(--linea-4); color: var(--sobre-linea)">4</span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-panel text-base font-bold uppercase leading-none">Barrio Norte</p>
                <p class="rotulo-digitos mt-2 text-[3.4rem]">
                    <span :key="marca" class="parpadeo-panel inline-block" x-text="String(n).padStart(2, '0')">07</span><span class="ml-2 text-xl font-bold">min</span>
                </p>
            </div>
        </div>
    </div>

    <div class="calzada-discontinua my-12 text-texto" aria-hidden="true"></div>

    {{-- 3. Pintura de la calzada --}}
    <div>
        <h3 class="font-titulo text-[2rem] font-extrabold leading-none">La pintura de la calzada</h3>
        <p class="mt-3 max-w-[60ch]">Las divisiones del sitio son líneas discontinuas, como las que separan carriles. La doble continua amarilla separa las secciones grandes, como las de esta página.</p>
        <div class="mt-6 space-y-6">
            <div class="calzada-discontinua text-texto" aria-hidden="true"></div>
            <div class="calzada-doble" aria-hidden="true"></div>
        </div>
    </div>

    <div class="calzada-discontinua my-12 text-texto" aria-hidden="true"></div>

    {{-- 4. Plano de recorridos --}}
    <div>
        <h3 class="font-titulo text-[2rem] font-extrabold leading-none">El plano de recorridos</h3>
        <p class="mt-3 max-w-[60ch]">Cada línea se dibuja como en un plano de subte: una recta con un círculo hueco en cada parada. La distancia entre círculos sigue la distancia real del tramo.</p>

        <ol class="mt-8 space-y-9">
            @foreach ($planos as $n => $plano)
                @php $max = max(array_column($plano['paradas'], 1)); @endphp
                <li class="grid items-start gap-3 sm:grid-cols-[7rem_1fr]">
                    <div class="flex items-center gap-3 sm:block">
                        <span class="flex h-14 w-14 items-center justify-center rounded-sm font-titulo text-[2.4rem] font-black leading-none" style="background: var(--linea-{{ $n }}); color: var(--sobre-linea)">{{ $n }}</span>
                        <span class="font-titulo text-xl font-extrabold leading-none sm:mt-2 sm:block">{{ $plano['destino'] }}</span>
                    </div>
                    <div class="relative ml-3 mr-16 h-16 sm:mr-24" aria-label="Paradas de la línea {{ $n }}: {{ implode(', ', array_column($plano['paradas'], 0)) }}" role="img">
                        <div class="absolute left-0 right-0 top-[10px] h-[5px]" style="background: var(--linea-{{ $n }})"></div>
                        @foreach ($plano['paradas'] as [$nombre, $pos])
                            @php $pct = $max ? ($pos / $max) * 100 : 0; @endphp
                            <div class="absolute top-0" style="left: calc({{ $pct }}% * (1 - 0)); transform: translateX(-50%)">
                                <span class="block h-[25px] w-[25px] rounded-full border-[5px] bg-fondo" style="border-color: var(--linea-{{ $n }})"></span>
                                <span class="absolute left-1/2 top-8 -translate-x-1/2 whitespace-nowrap text-[0.8rem] font-semibold leading-none">{{ $nombre }}</span>
                            </div>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="calzada-discontinua my-12 text-texto" aria-hidden="true"></div>

    {{-- 5. El colectivo --}}
    <div class="grid items-center gap-8 md:grid-cols-[1fr_22rem]" x-data="{ rumbo: 24, linea: 1 }">
        <div>
            <h3 class="font-titulo text-[2rem] font-extrabold leading-none">El colectivo, visto desde arriba</h3>
            <p class="mt-3 max-w-[56ch]">Gira según su rumbo y lleva el color de su línea. Mueve el control: en el mapa, el giro llega suave, por el arco corto.</p>

            <label class="mt-6 block max-w-sm font-semibold" for="rumbo">Rumbo: <span x-text="rumbo + ' grados'">24 grados</span></label>
            <input id="rumbo" type="range" min="0" max="359" x-model.number="rumbo" class="mt-2 w-full max-w-sm accent-[var(--senal)]">

            <div class="mt-5 flex gap-2" role="group" aria-label="Color de línea">
                @foreach ([1, 2, 3, 4, 5] as $n)
                    <button type="button" @click="linea = {{ $n }}" :aria-pressed="linea === {{ $n }}"
                            class="h-10 w-10 rounded-sm font-titulo text-xl font-black leading-none" :class="linea === {{ $n }} ? 'outline outline-[3px] outline-offset-2 outline-texto' : ''"
                            style="background: var(--linea-{{ $n }}); color: var(--sobre-linea)">{{ $n }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex h-72 items-center justify-center bg-superficie" aria-hidden="true">
            <svg viewBox="-16 -16 32 32" class="h-56 w-56 transition-transform duration-100 ease-out"
                 :style="'--l: var(--linea-' + linea + '); transform: rotate(' + rumbo + 'deg)'">
                <use href="#colectivo-arriba" x="-12" y="-6" width="24" height="12"/>
            </svg>
        </div>
    </div>
</section>

<x-colectivos-linea class="mx-auto max-w-5xl px-4 text-apoyo opacity-80" />

{{-- ===================== LOGO ===================== --}}
<section id="logo" class="mx-auto max-w-7xl px-4 py-16 sm:px-8">
    <div class="calzada-doble mb-12" aria-hidden="true"></div>
    <h2 class="font-titulo text-[clamp(2.8rem,6vw,4.8rem)] font-black leading-[0.9]">Un ramal</h2>
    <p class="mt-4 max-w-[58ch]">Una línea que sale de una parada y se bifurca en dos. Funciona en una sola tinta y es legible a 16 px como favicon.</p>

    <div class="mt-10 grid gap-6 md:grid-cols-2">
        <div class="flex flex-wrap items-center gap-x-8 gap-y-6 bg-papel p-8 text-asfalto">
            <div class="flex items-center gap-4">
                <x-isotipo :tamano="96" />
                <span class="font-titulo text-[4.4rem] font-extrabold leading-none">Ramal</span>
            </div>
            <div class="flex items-end gap-5">
                <x-isotipo :tamano="64" /><x-isotipo :tamano="32" /><x-isotipo :tamano="16" />
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-6 bg-asfalto p-8 text-papel">
            <div class="flex items-center gap-4">
                <x-isotipo :tamano="96" />
                <span class="font-titulo text-[4.4rem] font-extrabold leading-none">Ramal</span>
            </div>
            <div class="flex items-end gap-5">
                <x-isotipo :tamano="64" /><x-isotipo :tamano="32" /><x-isotipo :tamano="16" />
            </div>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-6">
        <div class="cartel-parada flex h-24 w-24 items-center justify-center rounded-sm"><x-isotipo :tamano="64" /></div>
        <div class="flex items-end gap-4">
            <img src="/favicon.svg" width="64" height="64" alt="">
            <img src="/favicon.svg" width="32" height="32" alt="">
            <img src="/favicon.svg" width="16" height="16" alt="Favicon de Ramal a 16 px">
        </div>
        <p class="max-w-[44ch] text-apoyo">El favicon es el isotipo en Asfalto sobre el amarillo del cartel de parada.</p>
    </div>
</section>

</main>

@include('partials.pie')
@endsection
