@extends('layouts.base')

@section('titulo', 'Acceso a la API, Ramal')
@section('descripcion', 'Tokens personales para usar la API pública de Ramal.')

@section('cuerpo')
@include('partials.cabecera')

@php
    $nuevo = session('token_nuevo');
    $lugares = max(0, $maximo - $tokens->count());
@endphp

<main id="contenido" x-data="{ copiado: false, copiar() { navigator.clipboard?.writeText(this.$refs.valor.textContent.trim()).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2500); }); } }"
      class="mx-auto max-w-5xl px-4 pb-20 pt-4 sm:px-8">

    <a href="{{ route('operador') }}" class="inline-flex items-center gap-1.5 font-semibold underline decoration-2 underline-offset-4"><x-icono nombre="operador" :tamano="18" /> Volver al centro de control</a>

    <div class="entra-campo mt-4 flex flex-wrap items-end justify-between gap-x-8 gap-y-3" style="--i: 0">
        <h1 class="font-titulo text-[clamp(3rem,7vw,5.6rem)] font-black leading-[0.88]">Acceso a la API</h1>
        <a href="{{ route('api') }}" class="cartel-parada flex items-center gap-2 rounded-sm px-4 py-2 font-titulo text-xl font-extrabold leading-none"><x-icono nombre="api" :tamano="20" /> Ver la documentación</a>
    </div>
    <p class="entra-campo mt-3 max-w-[60ch] text-lg text-apoyo" style="--i: 1">
        Un token deja que un programa tuyo lea líneas, paradas, posiciones y llegadas. Cada uno aguanta {{ $limite }} pedidos por minuto y puede revocarse cuando quieras.
    </p>

    @if (session('estado'))
        <p class="mt-5 flex items-center gap-2 border-[3px] border-texto bg-senal p-3 font-semibold text-asfalto" role="status"><x-icono nombre="en-hora" :tamano="22" /> {{ session('estado') }}</p>
    @endif

    {{-- El token recién creado: se ve una sola vez --}}
    @if ($nuevo)
        <section class="tema-noche tablero tablero-con-calzada entra-campo relative mt-6 overflow-hidden rounded-sm px-5 pb-10 pt-5 sm:px-7 sm:pb-12 sm:pt-7" style="--i: 2" aria-labelledby="titulo-nuevo" role="status">
            <p class="flex items-center gap-2 font-semibold text-senal"><x-icono nombre="alerta" :tamano="22" /> Copialo ahora: no lo vas a poder volver a ver</p>
            <h2 id="titulo-nuevo" class="mt-2 font-titulo text-[2.2rem] font-black leading-none">{{ $nuevo['nombre'] }}</h2>

            <div class="mt-4 flex flex-wrap items-stretch gap-3">
                <p x-ref="valor" class="codigo min-w-0 flex-1 select-all break-all rounded-sm border-2 bg-asfalto px-3 py-3 text-senal" style="border-color: color-mix(in srgb, var(--senal) 60%, transparent)">{{ $nuevo['valor'] }}</p>
                <button type="button" @click="copiar()" class="cartel-parada flex items-center gap-2 rounded-sm px-5 py-2 font-titulo text-xl font-extrabold leading-none">
                    <span x-show="!copiado" class="flex items-center gap-2"><x-icono nombre="api" :tamano="20" /> Copiar</span>
                    <span x-show="copiado" x-cloak class="flex items-center gap-2"><x-icono nombre="en-hora" :tamano="20" /> Copiado</span>
                </button>
            </div>

            <p class="mt-4 text-sm text-papel/75">Probalo con:</p>
            <pre class="codigo-bloque mt-1">curl -H "Authorization: Bearer {{ $nuevo['valor'] }}" {{ url('/api/v1/colectivos') }}</pre>
        </section>
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_20rem]">
        {{-- Los tokens que ya existen --}}
        <section aria-labelledby="titulo-tokens" class="entra-campo" style="--i: 3">
            <h2 id="titulo-tokens" class="font-titulo text-[2.2rem] font-black leading-none">Tus tokens</h2>
            <div class="calzada-discontinua mt-3 text-texto/60" aria-hidden="true"></div>

            @forelse ($tokens as $i => $token)
                <article class="entra-campo mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 border-[3px] border-texto p-4" style="--i: {{ $i + 4 }}">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm bg-texto text-fondo"><x-icono nombre="candado" :tamano="24" /></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-titulo text-2xl font-extrabold leading-none">{{ $token->name }}</h3>
                        <p class="mt-1 text-sm text-apoyo">
                            Creado el {{ $token->created_at->timezone(config('ramal.zona_horaria'))->format('d/m/Y H:i') }}.
                            @if ($token->last_used_at)
                                Último uso {{ $token->last_used_at->diffForHumans() }}.
                            @else
                                Todavía no se usó.
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('operador.api.revocar', $token->id) }}" onsubmit="return confirm({{ Illuminate\Support\Js::from('¿Revocar «'.$token->name.'»? Los programas que lo usen dejan de funcionar.') }})">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex items-center gap-2 rounded-full border-2 border-texto px-4 py-2 font-semibold hover:bg-texto hover:text-fondo"><x-icono nombre="cerrar" :tamano="18" /> Revocar</button>
                    </form>
                </article>
            @empty
                <div class="mt-4 border-[3px] border-dashed border-texto p-6 text-center">
                    <x-icono nombre="candado" :tamano="40" class="mx-auto" />
                    <p class="mt-3 font-titulo text-3xl font-extrabold leading-none">Todavía no creaste ningún token</p>
                    <p class="mt-2 text-apoyo">Creá el primero con el formulario y copialo: se muestra una sola vez.</p>
                </div>
            @endforelse
        </section>

        {{-- Crear uno nuevo --}}
        <section aria-labelledby="titulo-crear" class="entra-campo self-start border-[3px] border-texto p-5" style="--i: 4">
            <h2 id="titulo-crear" class="font-titulo text-[2rem] font-black leading-none">Nuevo token</h2>
            <p class="mt-1 text-sm text-apoyo">Te quedan {{ $lugares }} de {{ $maximo }}.</p>

            <form method="POST" action="{{ route('operador.api.crear') }}" class="mt-4 space-y-4" novalidate>
                @csrf
                <div>
                    <label for="nombre" class="block font-semibold">Para qué lo vas a usar</label>
                    <div class="campo mt-1.5" @error('nombre') data-error @enderror>
                        <x-icono nombre="api" :tamano="22" class="text-apoyo" />
                        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" maxlength="60" placeholder="Mi aplicación" autocomplete="off" required
                               @error('nombre') aria-invalid="true" aria-describedby="error-nombre" @enderror>
                    </div>
                    @error('nombre')
                        <p id="error-nombre" class="mt-2 flex items-start gap-2 font-semibold" role="alert"><x-icono nombre="alerta" :tamano="20" class="mt-0.5 shrink-0" /> {{ $message }}</p>
                    @enderror
                </div>
                <p class="flex items-start gap-2 text-sm text-apoyo"><x-icono nombre="ojo" :tamano="18" class="mt-0.5 shrink-0" /> Permiso de solo lectura: no puede cambiar nada.</p>
                <button type="submit" class="cartel-parada flex w-full items-center justify-center gap-2 rounded-sm px-5 py-3 font-titulo text-2xl font-extrabold leading-none disabled:opacity-50" @disabled($lugares === 0)>
                    <x-icono nombre="mas" :tamano="22" /> Crear token
                </button>
            </form>
        </section>
    </div>
</main>
@endsection
