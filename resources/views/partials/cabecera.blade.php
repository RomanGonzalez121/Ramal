<div class="calzada-doble" aria-hidden="true"></div>

<header class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-4 sm:gap-4 sm:px-8">
    <a href="{{ route('mapa') }}" class="flex items-center gap-2.5" aria-label="Ramal, inicio">
        <x-isotipo :tamano="30" class="sm:h-[34px] sm:w-[34px]" />
        <span class="font-titulo text-[1.6rem] font-extrabold leading-none sm:text-[1.9rem]">Ramal</span>
    </a>

    <nav aria-label="Principal" class="flex items-center gap-2.5 text-[0.85rem] font-medium sm:gap-6 sm:text-[0.95rem]">
        <a class="hover:underline hover:decoration-[3px] hover:underline-offset-4" href="{{ route('mapa') }}" @if (request()->routeIs('mapa')) aria-current="page" @endif>Mapa</a>
        <a class="hidden hover:underline hover:decoration-[3px] hover:underline-offset-4 sm:inline" href="{{ route('identidad') }}" @if (request()->routeIs('identidad')) aria-current="page" @endif>Identidad</a>
        <a class="hover:underline hover:decoration-[3px] hover:underline-offset-4" href="{{ route('lineas') }}" @if (request()->routeIs('lineas')) aria-current="page" @endif>Líneas</a>
        <a class="hover:underline hover:decoration-[3px] hover:underline-offset-4" href="{{ route('api') }}" @if (request()->routeIs('api')) aria-current="page" @endif>API</a>
        <a class="hover:underline hover:decoration-[3px] hover:underline-offset-4" href="{{ route('operador') }}" @if (request()->routeIs('operador*', 'login')) aria-current="page" @endif>Operador</a>
        @foreach ($enlaces ?? [] as $ancla => $texto)
            <a class="hidden hover:underline hover:decoration-[3px] hover:underline-offset-4 lg:inline" href="#{{ $ancla }}">{{ $texto }}</a>
        @endforeach
    </nav>

    <button type="button" x-data="tema" @click="alternar($event)"
            class="flex items-center gap-2 rounded-full border-2 border-texto px-2.5 py-2 text-[0.95rem] font-semibold sm:px-4 transition-colors duration-100 hover:bg-texto hover:text-fondo"
            :aria-label="actual === 'noche' ? 'Cambiar a tema Día' : 'Cambiar a tema Noche'">
        <template x-if="actual === 'noche'"><x-icono nombre="sol" :tamano="20" /></template>
        <template x-if="actual !== 'noche'"><x-icono nombre="luna" :tamano="20" /></template>
        <span class="hidden sm:inline" x-text="actual === 'noche' ? 'Día' : 'Noche'">Noche</span>
    </button>
</header>
