@php
    // Los destinos del sitio, pensados para quien llega sin saber nada: primero lo que usa cualquier visitante,
    // después lo que sirve para programar o revisar el diseño, y aparte la entrada de los operadores.
    $principales = [
        ['mapa', 'mapa', 'Mapa', 'Los colectivos en vivo', ['mapa']],
        ['lineas', 'linea', 'Líneas', 'Los 5 recorridos y sus paradas', ['lineas']],
        ['como-funciona', 'reloj', 'Cómo funciona', 'Qué es simulado y qué es real', ['como-funciona']],
    ];
    $desarrolladores = [
        ['api', 'api', 'Datos abiertos (API)', 'Posiciones y llegadas para tus programas', ['api']],
        ['identidad', 'ramal', 'Identidad visual', 'Logo, colores, tipografías y piezas', ['identidad']],
    ];
    $estaEn = fn (array $destino) => request()->routeIs(...$destino[4]);
    $enDesarrolladores = collect($desarrolladores)->contains($estaEn);
    $operador = ['operador', 'operador', auth()->check() ? 'Centro de control' : 'Ingresar', 'Solo para operadores', ['operador*', 'login']];
@endphp

<div class="calzada-doble" aria-hidden="true"></div>

<header x-data="navegacion" @keydown.escape.window="cerrar()" class="relative z-40">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-8">
        <a href="{{ route('mapa') }}" class="flex shrink-0 items-center gap-2.5" aria-label="Ramal, inicio">
            <x-isotipo :tamano="30" class="sm:h-[34px] sm:w-[34px]" />
            <span class="font-titulo text-[1.6rem] font-extrabold leading-none sm:text-[1.9rem]">Ramal</span>
        </a>

        {{-- Escritorio: los destinos agrupados --}}
        <nav aria-label="Principal" class="hidden items-center gap-7 md:flex">
            @foreach ($principales as $destino)
                <a href="{{ route($destino[0]) }}" class="nav-enlace" @if ($estaEn($destino)) aria-current="page" @endif>{{ $destino[2] }}</a>
            @endforeach

            <div class="relative" @click.outside="dev = false">
                <button type="button" @click="dev = !dev" :aria-expanded="dev" aria-controls="menu-desarrolladores" class="nav-enlace flex items-center gap-1.5" @if ($enDesarrolladores) data-activo @endif>
                    Para desarrolladores
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="square" aria-hidden="true" class="transition-transform duration-150" :class="dev ? 'rotate-180' : ''"><path d="M5 9l7 7 7-7"/></svg>
                </button>

                <div id="menu-desarrolladores" x-show="dev" x-cloak
                     x-transition:enter="transition duration-150 ease-out motion-reduce:duration-75" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition duration-100 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute left-1/2 top-[calc(100%+0.9rem)] w-[21rem] -translate-x-1/2 border-[3px] border-texto bg-fondo p-2" style="box-shadow: 5px 5px 0 var(--sombra-cartel)">
                    @foreach ($desarrolladores as $destino)
                        <a href="{{ route($destino[0]) }}" @if ($estaEn($destino)) aria-current="page" @endif
                           class="menu-destino flex items-start gap-3 p-3">
                            <span class="menu-destino-icono flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border-2 border-texto"><x-icono :nombre="$destino[1]" :tamano="22" /></span>
                            <span class="min-w-0"><span class="block font-titulo text-[1.5rem] font-extrabold leading-none">{{ $destino[2] }}</span><span class="mt-1 block text-sm text-apoyo">{{ $destino[3] }}</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
            {{-- Escritorio: la entrada de operadores, aparte de los destinos del visitante --}}
            <a href="{{ route($operador[0]) }}" @if ($estaEn($operador)) aria-current="page" @endif
               class="boton-operador hidden items-center gap-2 rounded-full border-2 border-texto px-4 py-2 text-[0.95rem] font-semibold md:flex">
                <x-icono :nombre="$operador[1]" :tamano="20" /> {{ $operador[2] }}
            </a>

            <button type="button" x-data="tema" @click="alternar($event)"
                    class="flex items-center gap-2 rounded-full border-2 border-texto px-2.5 py-2 text-[0.95rem] font-semibold transition-colors duration-100 hover:bg-texto hover:text-fondo sm:px-4"
                    :aria-label="actual === 'noche' ? 'Cambiar a tema Día' : 'Cambiar a tema Noche'">
                <template x-if="actual === 'noche'"><x-icono nombre="sol" :tamano="20" /></template>
                <template x-if="actual !== 'noche'"><x-icono nombre="luna" :tamano="20" /></template>
                <span class="hidden sm:inline" x-text="actual === 'noche' ? 'Día' : 'Noche'">Noche</span>
            </button>

            {{-- Celular: todo detrás de un botón que dice lo que es --}}
            <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-controls="menu-celular"
                    class="flex items-center gap-2 rounded-full border-2 border-texto bg-texto px-3.5 py-2 text-[0.95rem] font-semibold text-fondo md:hidden">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true">
                    <path x-show="!menu" d="M4 7h16M4 12h16M4 17h16"/><path x-show="menu" x-cloak d="M6 6l12 12M18 6 6 18"/>
                </svg>
                <span x-text="menu ? 'Cerrar' : 'Menú'">Menú</span>
            </button>
        </div>
    </div>

    {{-- Celular: el menú completo, con una línea que explica cada destino --}}
    <nav id="menu-celular" aria-label="Menú" x-show="menu" x-cloak @click.outside="menu = false"
         x-transition:enter="transition duration-200 ease-out motion-reduce:duration-75" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition duration-100 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-x-3 top-[calc(100%-0.25rem)] max-h-[80dvh] overflow-y-auto border-[3px] border-texto bg-fondo p-2 md:hidden" style="box-shadow: 5px 5px 0 var(--sombra-cartel)">
        @foreach ($principales as $destino)
            <a href="{{ route($destino[0]) }}" @if ($estaEn($destino)) aria-current="page" @endif class="menu-destino flex items-center gap-3 p-3">
                <span class="menu-destino-icono flex h-11 w-11 shrink-0 items-center justify-center rounded-sm border-2 border-texto"><x-icono :nombre="$destino[1]" :tamano="24" /></span>
                <span class="min-w-0"><span class="block font-titulo text-[1.7rem] font-extrabold leading-none">{{ $destino[2] }}</span><span class="mt-0.5 block text-sm text-apoyo">{{ $destino[3] }}</span></span>
            </a>
        @endforeach

        <div class="calzada-discontinua my-2 text-texto/50" aria-hidden="true"></div>
        <p class="px-3 pb-1 pt-2 font-semibold text-apoyo">Para desarrolladores</p>
        @foreach ($desarrolladores as $destino)
            <a href="{{ route($destino[0]) }}" @if ($estaEn($destino)) aria-current="page" @endif class="menu-destino flex items-center gap-3 p-3">
                <span class="menu-destino-icono flex h-11 w-11 shrink-0 items-center justify-center rounded-sm border-2 border-texto"><x-icono :nombre="$destino[1]" :tamano="24" /></span>
                <span class="min-w-0"><span class="block font-titulo text-[1.7rem] font-extrabold leading-none">{{ $destino[2] }}</span><span class="mt-0.5 block text-sm text-apoyo">{{ $destino[3] }}</span></span>
            </a>
        @endforeach

        <div class="calzada-discontinua my-2 text-texto/50" aria-hidden="true"></div>
        <a href="{{ route($operador[0]) }}" @if ($estaEn($operador)) aria-current="page" @endif class="menu-destino flex items-center gap-3 p-3">
            <span class="menu-destino-icono flex h-11 w-11 shrink-0 items-center justify-center rounded-sm border-2 border-texto"><x-icono :nombre="$operador[1]" :tamano="24" /></span>
            <span class="min-w-0"><span class="block font-titulo text-[1.7rem] font-extrabold leading-none">{{ $operador[2] }}</span><span class="mt-0.5 block text-sm text-apoyo">{{ $operador[3] }}</span></span>
        </a>
    </nav>
</header>

{{-- Los atajos de la página donde estás, aparte de la navegación del sitio --}}
@if (! empty($enlaces))
    <nav aria-label="En esta página" class="mx-auto flex max-w-7xl items-center gap-2 overflow-x-auto px-4 pb-4 sm:px-8">
        <span class="shrink-0 pr-1 text-sm font-semibold text-apoyo">En esta página</span>
        @foreach ($enlaces as $ancla => $texto)
            <a href="#{{ $ancla }}" class="shrink-0 rounded-full border-2 border-texto/40 px-3 py-1 text-sm font-semibold hover:border-texto hover:bg-texto hover:text-fondo">{{ $texto }}</a>
        @endforeach
    </nav>
@endif
