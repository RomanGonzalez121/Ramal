@extends('layouts.base')

@section('titulo', 'Ramal, colectivos de Paraná en vivo')
@section('descripcion', 'Mirá dónde está cada colectivo de Paraná ahora. Las líneas y los colectivos son simulados; las calles y el tiempo real, no.')

@section('cuerpo')
@include('partials.cabecera')

<main id="contenido">
<section x-data="mapaPublico" class="relative h-[calc(100dvh-5.1rem)] min-h-[34rem] overflow-hidden border-t-[3px] border-texto bg-superficie">

    {{-- El mapa y, encima, los colectivos y las paradas --}}
    <div class="absolute inset-0" aria-hidden="true"><div x-ref="mapa" class="h-full w-full"></div></div>
    <svg x-ref="ruta" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true"></svg>
    <div x-ref="capa" class="pointer-events-none absolute inset-0 overflow-hidden"></div>

    {{-- Panel --}}
    <aside class="pointer-events-none absolute inset-x-3 bottom-3 z-10 flex max-h-[52dvh] flex-col gap-3 overflow-y-auto md:inset-y-4 md:bottom-4 md:left-4 md:right-auto md:max-h-none md:w-[22rem]" aria-label="Información del mapa">
        {{-- Tablero: el rótulo luminoso de la ciudad --}}
        <div class="tema-noche tablero pointer-events-auto overflow-hidden rounded-sm border-[3px] border-asfalto p-4 shadow-none" style="border-color: color-mix(in srgb, var(--papel) 22%, var(--asfalto))">
            <div class="flex items-start justify-between gap-3">
                <h1 class="font-titulo text-[2.6rem] font-black leading-[0.9]">Paraná, <span x-text="modo === 'pasado' ? 'antes' : 'ahora'">ahora</span></h1>
                <p class="mt-1 flex shrink-0 items-center gap-1.5 rounded-full border-2 px-2.5 py-1 text-xs font-semibold" style="border-color: color-mix(in srgb, var(--papel) 35%, transparent)" role="status" aria-live="polite">
                    <template x-if="modo === 'pasado'"><span class="flex items-center gap-1.5 text-senal"><x-icono nombre="rebobinar" :tamano="16" /> Grabado</span></template>
                    <template x-if="modo === 'vivo' && conexion === 'en-vivo' && !sinNovedades"><span class="flex items-center gap-1.5 text-senal"><x-icono nombre="en-vivo" :tamano="16" /> En vivo</span></template>
                    <template x-if="modo === 'vivo' && (conexion === 'sondeo' && !sinNovedades)"><span class="flex items-center gap-1.5"><x-icono nombre="reloj" :tamano="16" /> Cada 2 s</span></template>
                    <template x-if="modo === 'vivo' && (conexion === 'conectando')"><span class="flex items-center gap-1.5"><x-icono nombre="reloj" :tamano="16" /> Conectando</span></template>
                    <template x-if="modo === 'vivo' && (conexion === 'sin-conexion' || sinNovedades)"><span class="flex items-center gap-1.5"><x-icono nombre="sin-senal" :tamano="16" /> Sin datos</span></template>
                </p>
            </div>

            <div class="mt-3 flex items-end gap-3">
                <p class="font-panel text-[4rem] font-bold leading-none text-senal" x-text="cantidad || '--'" aria-hidden="true">40</p>
                <p class="pb-1 text-sm leading-tight text-papel/80"><span class="sr-only" x-text="cantidad"></span>colectivos en la calle.<br><a href="{{ route('como-funciona') }}" class="font-semibold text-papel underline decoration-senal decoration-2 underline-offset-4 hover:bg-senal hover:text-asfalto">Simulados, sobre calles reales</a>.</p>
            </div>

            <div class="calzada-discontinua my-4 text-senal" aria-hidden="true"></div>

            <div role="group" aria-label="Elegir línea">
                <p class="font-semibold">Líneas</p>
                <div class="mt-2 flex items-center gap-2">
                    <template x-for="n in lineas" :key="n">
                        <button type="button" @click="elegirLinea(n)" :aria-pressed="linea === n" :aria-label="'Línea ' + n"
                                class="flex h-11 w-11 items-center justify-center rounded-sm font-titulo text-[1.6rem] font-black leading-none transition-transform duration-100 active:scale-95"
                                :class="linea === n ? 'outline outline-[3px] outline-offset-2 outline-texto' : ''"
                                :style="'background:var(--linea-' + n + ');color:var(--sobre-linea)'" x-text="n"></button>
                    </template>
                    <button type="button" x-show="linea !== null" @click="elegirLinea(linea)"
                            class="ml-1 rounded-full border-2 border-texto px-3 py-1.5 text-sm font-semibold transition-colors duration-100 hover:bg-texto hover:text-fondo">Ver todas</button>
                </div>
            </div>
        </div>

        {{-- Parada elegida --}}
        <div x-show="parada" x-cloak x-transition:enter="transition duration-200 ease-out motion-reduce:duration-100" x-transition:enter-start="opacity-0 translate-y-2 motion-reduce:translate-y-0" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="pointer-events-auto border-[3px] border-texto bg-fondo p-4">
            <div class="flex items-start gap-3">
                <div class="cartel-parada flex h-12 w-12 shrink-0 items-center justify-center rounded-sm" aria-hidden="true"><x-icono nombre="colectivo" :tamano="28" /></div>
                <div class="min-w-0 flex-1">
                    <p class="font-titulo text-[1.7rem] font-extrabold leading-none" x-text="parada?.nombre"></p>
                    <p class="mt-1 text-sm text-apoyo">Próximos colectivos</p>
                </div>
                <button type="button" @click="cerrarParada()" class="rounded-full border-2 border-texto p-1.5 hover:bg-texto hover:text-fondo" aria-label="Cerrar parada"><x-icono nombre="cerrar" :tamano="18" /></button>
            </div>

            <p x-show="modo === 'pasado'" x-cloak class="mt-3 flex items-center gap-2 text-sm font-semibold"><x-icono nombre="reloj" :tamano="18" /> Las llegadas se ven solo en vivo.</p>

            <div class="mt-3" x-show="modo === 'vivo'">
                <template x-if="llegadasEstado === 'cargando'">
                    <p class="rotulo rounded-sm px-3 py-3 font-panel text-lg font-bold" role="status">Calculando</p>
                </template>

                <template x-if="llegadasEstado === 'error'">
                    <p class="flex items-center gap-2 text-sm font-semibold" role="alert"><x-icono nombre="sin-senal" :tamano="20" /> No pudimos calcular las llegadas. Probamos de nuevo en unos segundos.</p>
                </template>

                <template x-if="llegadasEstado === 'listo' && filas.length === 0">
                    <div class="rounded-sm border-2 border-dashed border-texto p-3 text-sm" role="status">
                        <p class="flex items-center gap-2 font-semibold"><x-icono nombre="reloj" :tamano="20" /> Hoy ya no pasan más colectivos por acá.</p>
                        <p class="mt-1 text-apoyo" x-show="proximoServicio">El servicio vuelve a las <span x-text="proximoServicio"></span>.</p>
                    </div>
                </template>

                <template x-if="llegadasEstado === 'listo' && filas.length > 0">
                    <div>
                        {{-- El próximo, en grande: el rótulo luminoso --}}
                        <div class="rotulo flex items-center gap-3 rounded-sm px-3 py-2.5">
                            <span class="flex h-12 min-w-12 items-center justify-center rounded-sm px-2 font-titulo text-[2.1rem] font-black leading-none"
                                  :style="'background:var(--linea-' + filas[0].linea + ');color:var(--sobre-linea)'" x-text="filas[0].linea"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-panel text-sm font-bold uppercase leading-none" x-text="filas[0].destino"></p>
                                <p class="rotulo-digitos mt-1.5 leading-none" :class="filas[0].llegando ? 'text-[1.9rem]' : 'text-[2.7rem]'">
                                    <template x-for="t in [filas[0].texto]" :key="t"><span class="parpadeo-panel inline-block" x-text="t"></span></template><span x-show="!filas[0].llegando" class="ml-2 text-lg font-bold">min</span>
                                </p>
                            </div>
                        </div>
                        <p class="sr-only" role="status" aria-live="polite" x-text="filas[0].anuncio"></p>
                        <p class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold" x-show="filas[0].incierta || filas[0].estado === 'demorado' || filas[0].estado === 'fuera_de_recorrido'">
                            <template x-if="filas[0].incierta"><span class="flex items-center gap-1.5"><x-icono nombre="incidente" :tamano="16" /> Con una falla: el horario puede cambiar</span></template>
                            <template x-if="!filas[0].incierta && filas[0].estado === 'demorado'"><span class="flex items-center gap-1.5"><x-icono nombre="demora" :tamano="16" /> Viene demorado</span></template>
                            <template x-if="!filas[0].incierta && filas[0].estado === 'fuera_de_recorrido'"><span class="flex items-center gap-1.5"><x-icono nombre="fuera-de-recorrido" :tamano="16" /> Va por otras calles: puede tardar más</span></template>
                        </p>

                        {{-- Los que siguen --}}
                        <ul class="mt-2 space-y-1.5">
                            <template x-for="f in filas.slice(1, 5)" :key="f.interno + '-' + f.ramal_id">
                                <li class="flex items-center gap-2.5 text-sm">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-sm font-titulo text-lg font-black leading-none" :style="'background:var(--linea-' + f.linea + ');color:var(--sobre-linea)'" x-text="f.linea"></span>
                                    <span class="min-w-0 flex-1 truncate" x-text="f.destino"></span>
                                    <span class="flex items-center gap-1 font-semibold">
                                        <template x-if="f.incierta"><x-icono nombre="incidente" :tamano="14" /></template>
                                        <template x-if="!f.incierta && f.estado === 'demorado'"><x-icono nombre="demora" :tamano="14" /></template>
                                        <template x-if="!f.incierta && f.estado === 'fuera_de_recorrido'"><x-icono nombre="fuera-de-recorrido" :tamano="14" /></template>
                                        <span x-text="f.enParada || f.llegando ? f.texto : f.minutos + ' min'"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </div>

        {{-- Colectivo elegido --}}
        <div x-show="colectivo" x-cloak x-transition:enter="transition duration-200 ease-out motion-reduce:duration-100" x-transition:enter-start="opacity-0 translate-y-2 motion-reduce:translate-y-0" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="pointer-events-auto border-[3px] border-texto bg-fondo p-4" role="status" aria-live="polite">
            <div class="flex items-center gap-3">
                <span class="flex h-12 min-w-12 items-center justify-center rounded-sm px-2 font-titulo text-[2.1rem] font-black leading-none"
                      :style="'background:var(--linea-' + colectivo?.linea + ');color:var(--sobre-linea)'" x-text="colectivo?.linea"></span>
                <div class="min-w-0 flex-1">
                    <p class="font-titulo text-[1.5rem] font-extrabold leading-none">Colectivo <span x-text="colectivo?.interno"></span></p>
                    <p class="mt-1 truncate text-sm text-apoyo">Hacia <span x-text="colectivo?.destino"></span></p>
                    <p class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold">
                        <template x-if="colectivo?.estado === 'demorado'"><x-icono nombre="demora" :tamano="18" /></template>
                        <template x-if="colectivo?.estado === 'averiado'"><x-icono nombre="incidente" :tamano="18" /></template>
                        <template x-if="colectivo?.estado === 'fuera_de_recorrido'"><x-icono nombre="fuera-de-recorrido" :tamano="18" /></template>
                        <template x-if="!['demorado', 'averiado', 'fuera_de_recorrido'].includes(colectivo?.estado)"><x-icono nombre="en-hora" :tamano="18" /></template>
                        <span x-text="colectivo?.etiqueta"></span>
                        <span class="font-normal text-apoyo" x-show="colectivo?.velocidadKmh > 0">a <span x-text="colectivo?.velocidadKmh"></span> km/h</span>
                    </p>
                </div>
                <button type="button" @click="cerrarColectivo()" class="rounded-full border-2 border-texto p-1.5 hover:bg-texto hover:text-fondo" aria-label="Cerrar colectivo"><x-icono nombre="cerrar" :tamano="18" /></button>
            </div>
        </div>
    </aside>

    {{-- Rebobinar: ver el día como un video (M8) --}}
    <div class="absolute left-3 right-3 top-3 z-10 md:bottom-14 md:left-[24.5rem] md:right-[5.5rem] md:top-auto" role="region" aria-label="Ver el pasado">
        <template x-if="modo === 'vivo'">
            <button type="button" @click="rebobinar()"
                    class="tema-noche tablero flex items-center gap-3 rounded-sm border-2 px-4 py-2.5 text-left font-semibold" style="border-color: color-mix(in srgb, var(--papel) 28%, var(--asfalto))">
                <x-icono nombre="rebobinar" :tamano="24" class="text-senal" />
                <span>Rebobinar el día <span class="hidden font-normal text-papel/70 sm:inline">y mirar cómo se movió el servicio</span></span>
            </button>
        </template>

        <p x-show="sinHistorial" x-cloak x-transition.opacity.duration.150ms class="mt-2 inline-flex items-center gap-2 border-[3px] border-texto bg-fondo px-3 py-2 text-sm font-semibold" role="status">
            <x-icono nombre="reloj" :tamano="18" /> Todavía no hay historial guardado.
        </p>

        <div x-show="modo === 'pasado'" x-cloak
             x-transition:enter="transition duration-200 ease-out motion-reduce:duration-100" x-transition:enter-start="opacity-0 translate-y-2 motion-reduce:translate-y-0" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="tema-noche tablero overflow-hidden rounded-sm border-2 p-3 sm:p-4" style="border-color: color-mix(in srgb, var(--papel) 28%, var(--asfalto))">

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div class="flex items-center gap-3">
                    <span class="hidden items-center gap-1.5 rounded-full bg-senal px-3 py-1 text-sm font-bold text-asfalto lg:inline-flex"><x-icono nombre="rebobinar" :tamano="16" /> Viendo el pasado</span>
                    <p class="font-panel text-[clamp(1.6rem,3vw,2.2rem)] font-bold leading-none text-senal" aria-hidden="true" x-text="horaPasado">--:--:--</p>
                    <p class="text-sm leading-tight text-papel/80"><span class="font-semibold text-papel" x-text="diaPasado"></span><br><span x-text="haceCuanto"></span></p>
                </div>
                <button type="button" @click="volverAlVivo()" class="cartel-parada flex items-center gap-2 rounded-sm px-3 py-1.5 font-titulo text-lg font-extrabold leading-none sm:px-4 sm:py-2 sm:text-xl">
                    <x-icono nombre="en-vivo" :tamano="20" /> Volver al vivo
                </button>
            </div>

            <p x-show="sinRegistro" x-cloak class="mt-3 flex items-center gap-2 border-l-[6px] border-senal bg-papel/10 px-3 py-2 text-sm font-semibold" role="status"><x-icono nombre="sin-senal" :tamano="20" /> No se guardó registro de este momento. Mové la línea o apretá reproducir para seguir.</p>

            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 sm:mt-3 sm:gap-x-5">
                <div class="flex items-center gap-1">
                    <button type="button" @click="saltar(-10)" class="flex h-11 w-11 items-center justify-center rounded-full border-2 hover:bg-papel hover:text-asfalto" style="border-color: color-mix(in srgb, var(--papel) 45%, transparent)" aria-label="Retroceder 10 minutos"><x-icono nombre="rebobinar" :tamano="22" /></button>
                    <button type="button" @click="alternarReproduccion()" class="flex h-12 w-12 items-center justify-center rounded-full bg-senal text-asfalto" :aria-label="reproduciendo ? 'Pausar' : 'Reproducir'" :aria-pressed="reproduciendo">
                        <span x-show="!reproduciendo"><x-icono nombre="reproducir" :tamano="24" /></span>
                        <span x-show="reproduciendo" x-cloak><x-icono nombre="pausar" :tamano="24" /></span>
                    </button>
                    <button type="button" @click="saltar(10)" class="flex h-11 w-11 items-center justify-center rounded-full border-2 hover:bg-papel hover:text-asfalto" style="border-color: color-mix(in srgb, var(--papel) 45%, transparent)" aria-label="Avanzar 10 minutos"><x-icono nombre="rebobinar" :tamano="22" class="-scale-x-100" /></button>
                </div>

                <div class="flex items-center gap-1.5" role="group" aria-label="Velocidad">
                    <x-icono nombre="velocidad" :tamano="20" class="hidden text-papel/70 sm:block" />
                    <template x-for="v in [1, 4, 16, 60]" :key="v">
                        <button type="button" @click="fijarVelocidad(v)" :aria-pressed="velocidad === v"
                                class="rounded-full border-2 px-2.5 py-1 text-sm font-bold sm:px-3"
                                :class="velocidad === v ? 'border-senal bg-senal text-asfalto' : 'hover:bg-papel hover:text-asfalto'"
                                :style="velocidad === v ? '' : 'border-color: color-mix(in srgb, var(--papel) 45%, transparent)'"
                                x-text="v + '×'" :aria-label="'Velocidad ' + v + ' veces'"></button>
                    </template>
                </div>

                <p x-show="cargandoHistorial" x-cloak class="flex items-center gap-1.5 text-sm text-papel/80" role="status"><x-icono nombre="reloj" :tamano="18" /> Cargando…</p>
            </div>

            <div class="mt-3">
                <input type="range" class="linea-de-tiempo" aria-label="Momento que se está viendo"
                       :min="rango?.desde" :max="rango?.hasta" step="10000" :value="tiempoPasado"
                       :aria-valuetext="diaPasado + ' a las ' + horaPasado"
                       @input="irA(Number($event.target.value))">
                <div class="mt-1 flex justify-between text-xs text-papel/65" aria-hidden="true">
                    <span x-text="rango ? new Date(rango.desde).toLocaleTimeString('es-AR', { timeZone: 'America/Argentina/Buenos_Aires', hour: '2-digit', minute: '2-digit', hour12: false }) : ''"></span>
                    <span>Ahora</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Zoom --}}
    <div class="absolute right-3 top-3 z-10 hidden flex-col border-[3px] border-texto bg-fondo md:flex">
        <button type="button" @click="acercar()" class="flex h-11 w-11 items-center justify-center border-b-[3px] border-texto hover:bg-texto hover:text-fondo" aria-label="Acercar el mapa"><x-icono nombre="mas" :tamano="22" /></button>
        <button type="button" @click="alejar()" class="flex h-11 w-11 items-center justify-center hover:bg-texto hover:text-fondo" aria-label="Alejar el mapa"><x-icono nombre="menos" :tamano="22" /></button>
    </div>

    {{-- Atribución --}}
    <p class="pointer-events-none absolute bottom-1 right-2 z-10 hidden max-w-[34rem] bg-fondo/85 px-2 py-0.5 text-right text-[0.72rem] leading-tight text-apoyo md:block">
        Mapa: OpenStreetMap (ODbL) y Protomaps. Recorridos: OSRM. Choferes, horarios y colectivos: simulados.
        <span x-show="medicion.fps" x-cloak><span x-text="medicion.fps"></span> cuadros por segundo</span>
    </p>

    {{-- Cargando y error --}}
    <div x-show="cargando" x-transition:leave="transition duration-200 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-fondo" role="status">
        <x-isotipo :tamano="64" />
        <p class="font-titulo text-3xl font-extrabold leading-none">Cargando el mapa</p>
    </div>
    <div x-show="error" x-cloak class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-4 bg-fondo px-6 text-center" role="alert">
        <x-icono nombre="sin-senal" :tamano="48" />
        <p class="font-titulo text-4xl font-extrabold leading-none">No pudimos cargar el mapa</p>
        <p class="max-w-[40ch] text-apoyo">Revisá tu conexión y volvé a intentar. Si sigue igual, probá más tarde.</p>
        <button type="button" onclick="location.reload()" class="rounded-full border-2 border-texto px-5 py-2 font-semibold hover:bg-texto hover:text-fondo">Volver a cargar</button>
    </div>
</section>
</main>
@endsection
