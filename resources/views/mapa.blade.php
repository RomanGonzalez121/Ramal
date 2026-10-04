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
                <h1 class="font-titulo text-[2.6rem] font-black leading-[0.9]">Paraná, ahora</h1>
                <p class="mt-1 flex shrink-0 items-center gap-1.5 rounded-full border-2 px-2.5 py-1 text-xs font-semibold" style="border-color: color-mix(in srgb, var(--papel) 35%, transparent)" role="status" aria-live="polite">
                    <template x-if="conexion === 'en-vivo' && !sinNovedades"><span class="flex items-center gap-1.5 text-senal"><x-icono nombre="en-vivo" :tamano="16" /> En vivo</span></template>
                    <template x-if="conexion === 'sondeo' && !sinNovedades"><span class="flex items-center gap-1.5"><x-icono nombre="reloj" :tamano="16" /> Cada 2 s</span></template>
                    <template x-if="conexion === 'conectando'"><span class="flex items-center gap-1.5"><x-icono nombre="reloj" :tamano="16" /> Conectando</span></template>
                    <template x-if="conexion === 'sin-conexion' || sinNovedades"><span class="flex items-center gap-1.5"><x-icono nombre="sin-senal" :tamano="16" /> Sin datos</span></template>
                </p>
            </div>

            <div class="mt-3 flex items-end gap-3">
                <p class="font-panel text-[4rem] font-bold leading-none text-senal" x-text="cantidad || '--'" aria-hidden="true">40</p>
                <p class="pb-1 text-sm leading-tight text-papel/80"><span class="sr-only" x-text="cantidad"></span>colectivos en la calle.<br>Simulados, sobre calles reales.</p>
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

            <div class="mt-3">
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

    {{-- Zoom --}}
    <div class="absolute right-3 top-3 z-10 flex flex-col border-[3px] border-texto bg-fondo">
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
