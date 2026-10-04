@extends('layouts.base')

@section('titulo', 'Ingresar al panel de operador')
@section('descripcion', 'Acceso al centro de control de Ramal, solo para operadores.')

@section('cuerpo')
@include('partials.cabecera')

<main id="contenido" class="grid min-h-[calc(100dvh-5.1rem)] border-t-[3px] border-texto lg:grid-cols-[1.05fr_1fr]"
      x-data="{ ver: false, mayus: false, email: @js(old('email', '')), clave: ''
                @if (config('ramal.mostrar_cuenta_demo'))
                , completar() { this.email = @js(\Database\Seeders\OperadorSeeder::CORREO_DEMO); this.clave = @js(\Database\Seeders\OperadorSeeder::CLAVE_DEMO); }
                @endif }">

    {{-- Tablero: el rótulo luminoso de la terminal, siempre sobre Asfalto --}}
    <section class="tema-noche tablero tablero-con-calzada relative flex flex-col justify-between gap-10 overflow-hidden px-6 py-10 text-papel sm:px-10 lg:px-14 lg:py-14" aria-labelledby="titulo-tablero">
        <div>
            <h1 id="titulo-tablero" class="parpadeo-panel font-panel text-[clamp(2.6rem,6.2vw,5.2rem)] font-bold uppercase leading-[0.95] text-senal">
                Centro de control
            </h1>
            <p class="mt-5 max-w-[42ch] text-lg text-papel/80">
                Desde acá los operadores ven qué colectivos van demorados o con problemas, atienden los incidentes y miran cómo viene el servicio.
            </p>
        </div>

        <div>
            <div class="calzada-discontinua mb-6 text-senal" aria-hidden="true"></div>

            <ul class="space-y-2.5" aria-label="Líneas del servicio">
                @foreach ($lineas as $i => $linea)
                    <li class="entra-campo flex items-center gap-4" style="--i: {{ $i + 3 }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm font-titulo text-[1.9rem] font-black leading-none"
                              style="background: var(--linea-{{ $linea->numero }}); color: var(--sobre-linea)">{{ $linea->numero }}</span>
                        <span class="min-w-0 flex-1 truncate font-panel text-lg font-bold uppercase leading-none text-papel">{{ $linea->destino }}</span>
                        <span class="hidden items-center gap-1.5 text-sm font-semibold text-papel/70 sm:flex"><x-icono nombre="en-hora" :tamano="18" /> En servicio</span>
                    </li>
                @endforeach
            </ul>

            <div class="calzada-discontinua mt-6 text-senal" aria-hidden="true"></div>
        </div>

        <div class="hidden lg:block">
            <x-colectivos-linea class="w-full text-papel/55" />
            <p class="mt-3 text-sm text-papel/65">Los colectivos, choferes e incidentes son simulados. Las calles y el seguimiento, no.</p>
        </div>
    </section>

    {{-- Formulario --}}
    <section class="flex items-center px-6 py-10 sm:px-10 lg:px-16" aria-labelledby="titulo-ingreso">
        <div class="mx-auto w-full max-w-md">
            <div class="entra-campo flex items-center gap-3" style="--i: 0">
                <span class="cartel-parada flex h-14 w-14 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="operador" :tamano="30" /></span>
                <div>
                    <h2 id="titulo-ingreso" class="font-titulo text-[3rem] font-black leading-[0.9]">Ingresar</h2>
                    <p class="text-apoyo">Con tu cuenta de operador</p>
                </div>
            </div>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" novalidate>
                @csrf

                <div class="entra-campo" style="--i: 1">
                    <label for="email" class="block font-semibold">Correo</label>
                    <div class="campo mt-1.5" @error('email') data-error @enderror>
                        <x-icono nombre="correo" :tamano="22" class="text-apoyo" />
                        <input id="email" name="email" type="email" x-model="email" autocomplete="username" inputmode="email" required autofocus
                               placeholder="nombre@ramal.test"
                               @error('email') aria-invalid="true" aria-describedby="error-ingreso" @enderror>
                    </div>
                </div>

                <div class="entra-campo" style="--i: 2">
                    <label for="password" class="block font-semibold">Contraseña</label>
                    <div class="campo mt-1.5" @error('email') data-error @enderror>
                        <x-icono nombre="candado" :tamano="22" class="text-apoyo" />
                        <input id="password" name="password" x-model="clave" :type="ver ? 'text' : 'password'" autocomplete="current-password" required
                               @keyup="mayus = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @keydown="mayus = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @blur="mayus = false">
                        <button type="button" @click="ver = !ver" :aria-pressed="ver" :aria-label="ver ? 'Ocultar la contraseña' : 'Mostrar la contraseña'"
                                class="-mr-1.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full hover:bg-superficie">
                            <span x-show="!ver"><x-icono nombre="ojo" :tamano="24" /></span>
                            <span x-show="ver" x-cloak><x-icono nombre="ojo-cerrado" :tamano="24" /></span>
                        </button>
                    </div>
                    <p x-show="mayus" x-cloak class="mt-2 flex items-center gap-1.5 text-sm font-semibold" role="status"><x-icono nombre="alerta" :tamano="18" /> Tenés las mayúsculas activadas.</p>
                </div>

                @if ($errors->any())
                    <p id="error-ingreso" class="entra-fila flex items-start gap-2 border-[3px] border-dashed border-texto p-3 font-semibold" role="alert">
                        <x-icono nombre="incidente" :tamano="22" class="mt-0.5" />
                        <span>{{ $errors->first() }}</span>
                    </p>
                @endif

                <label class="entra-campo flex cursor-pointer items-center gap-3" style="--i: 3">
                    <input type="checkbox" name="recordar" value="1" class="peer sr-only">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center border-[3px] border-texto bg-fondo peer-checked:bg-senal peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-senal [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100" aria-hidden="true">
                        <x-icono nombre="en-hora" :tamano="16" class="text-asfalto" />
                    </span>
                    <span>Mantener la sesión abierta en este equipo</span>
                </label>

                <div class="entra-campo" style="--i: 4">
                    <button type="submit" class="cartel-parada flex w-full items-center justify-center gap-3 rounded-sm px-6 py-3.5 font-titulo text-[1.9rem] font-extrabold leading-none">
                        Ingresar al panel
                    </button>
                </div>
            </form>

            @if (config('ramal.mostrar_cuenta_demo'))
                <aside class="entra-campo mt-8 border-[3px] border-dashed border-texto p-4" style="--i: 5" aria-labelledby="demo">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 id="demo" class="font-titulo text-[1.5rem] font-extrabold leading-none">Cuenta de demostración</h3>
                            <p class="mt-1.5 text-sm text-apoyo">Para mirar el panel sin registrarte. Todos los datos son simulados.</p>
                        </div>
                        <button type="button" @click="completar()" class="shrink-0 rounded-full border-2 border-texto px-3.5 py-1.5 text-sm font-semibold hover:bg-texto hover:text-fondo">Completar</button>
                    </div>
                    <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        <div><dt class="text-apoyo">Correo</dt><dd class="break-all font-semibold">{{ \Database\Seeders\OperadorSeeder::CORREO_DEMO }}</dd></div>
                        <div><dt class="text-apoyo">Contraseña</dt><dd class="font-semibold">{{ \Database\Seeders\OperadorSeeder::CLAVE_DEMO }}</dd></div>
                    </dl>
                </aside>
            @endif

            <a href="{{ route('mapa') }}" class="entra-campo mt-6 inline-flex items-center gap-2 font-semibold underline decoration-[3px] underline-offset-4" style="--i: 6"><x-icono nombre="mapa" :tamano="20" /> Volver al mapa</a>
        </div>
    </section>
</main>
@endsection
