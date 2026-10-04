@extends('layouts.base')

@section('titulo', 'Centro de control, Ramal')
@section('descripcion', 'Panel de operador: demoras, fallas, incidentes y gráficos del día.')

@section('cuerpo')
@include('partials.cabecera')

<main id="contenido" x-data="panelOperador" class="mx-auto max-w-7xl px-4 pb-20 pt-4 sm:px-8">

    {{-- Cabecera del panel: título, reloj y estado general --}}
    <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
        <div class="min-w-0">
            <h1 class="font-titulo text-[clamp(3rem,7vw,5.6rem)] font-black leading-[0.88]">Centro de control</h1>
            <p class="mt-2 text-apoyo">Hola, {{ auth()->user()->name }}. Los datos se actualizan solos.</p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div class="tema-noche flex items-center gap-3 rounded-sm bg-asfalto px-4 py-2.5" role="timer" aria-label="Hora en Paraná">
                <x-icono nombre="reloj" :tamano="22" class="text-papel/70" />
                <span class="font-panel text-[2rem] font-bold leading-none text-senal" x-text="reloj || '--:--:--'">--:--:--</span>
            </div>

            <a href="{{ route('operador.api') }}" class="flex items-center gap-2 rounded-full border-2 border-texto px-4 py-2.5 font-semibold hover:bg-texto hover:text-fondo"><x-icono nombre="api" :tamano="20" /> Acceso a la API</a>

            <form method="POST" action="{{ route('salir') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-full border-2 border-texto px-4 py-2.5 font-semibold hover:bg-texto hover:text-fondo"><x-icono nombre="operador" :tamano="20" /> Salir</button>
            </form>
        </div>
    </div>

    <p x-show="aviso" x-cloak x-transition.opacity.duration.150ms class="mt-5 flex items-center gap-2 border-[3px] border-texto bg-senal p-3 font-semibold text-asfalto" role="status">
        <x-icono nombre="alerta" :tamano="22" /> <span x-text="aviso"></span>
    </p>
    <p x-show="error" x-cloak class="mt-5 flex items-center gap-2 border-[3px] border-dashed border-texto p-3 font-semibold" role="alert">
        <x-icono nombre="sin-senal" :tamano="22" /> No pudimos actualizar los datos. Lo intentamos de nuevo en unos segundos.
    </p>

    {{-- Tablero: los números clave, como un rótulo luminoso --}}
    <section class="tema-noche tablero mt-6 overflow-hidden rounded-sm" aria-label="Indicadores del servicio">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-dashed border-papel/25 px-5 py-3">
            <p class="flex items-center gap-2 font-semibold" role="status">
                <template x-if="atencionCantidad === 0"><span class="flex items-center gap-2"><x-icono nombre="en-hora" :tamano="22" /> Servicio normal</span></template>
                <template x-if="atencionCantidad > 0"><span class="flex items-center gap-2 text-senal"><x-icono nombre="alerta" :tamano="22" /> <span x-text="atencionCantidad === 1 ? '1 colectivo necesita atención' : atencionCantidad + ' colectivos necesitan atención'"></span></span></template>
            </p>
            <p class="text-sm text-papel/65">Hoy: <span class="font-semibold text-papel" x-text="datos?.indicadores.incidentes_del_dia ?? '-'"></span> incidentes</p>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
            <template x-for="(k, i) in indicadores" :key="k.clave">
                <div class="flex flex-col justify-between gap-2 border-papel/20 px-5 py-4 transition-colors duration-200" :class="[i > 0 ? 'xl:border-l-2 xl:border-dashed' : '', k.alerta ? 'bg-senal text-asfalto' : '']">
                    <dt class="flex items-center gap-1.5 text-sm font-semibold" :class="k.alerta ? 'text-asfalto' : 'text-papel/75'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true" class="shrink-0"><use :href="'#i-' + k.icono"/></svg>
                        <span x-text="k.titulo"></span>
                    </dt>
                    <dd class="whitespace-nowrap font-panel text-[clamp(2.4rem,3.6vw,3.6rem)] font-bold leading-none" :class="k.alerta ? 'text-asfalto' : 'text-senal'" x-text="k.valor"></dd>
                </div>
            </template>
        </dl>
    </section>

    {{-- Plano en vivo + colectivos que necesitan atención --}}
    {{-- min-w-0: sin esto la columna se estira hasta el texto más largo y la página se sale de la pantalla en el celular --}}
    <div class="mt-12 grid gap-10 xl:grid-cols-[1.45fr_1fr]">
        <section aria-labelledby="titulo-plano" class="min-w-0">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="titulo-plano" class="font-titulo text-[2.6rem] font-black leading-none">Plano del servicio</h2>
                    <p class="mt-1 text-apoyo">Los 40 colectivos, ahora. Los que tienen problemas llevan su número.</p>
                </div>
                <div class="flex items-center gap-1.5" role="group" aria-label="Filtrar por línea">
                    <button type="button" @click="filtrar(null)" :aria-pressed="filtro === null"
                            class="rounded-full border-2 border-texto px-3 py-1.5 text-sm font-semibold" :class="filtro === null ? 'bg-texto text-fondo' : ''">Todas</button>
                    <template x-for="n in [1, 2, 3, 4, 5]" :key="n">
                        <button type="button" @click="filtrar(n)" :aria-pressed="filtro === n" :aria-label="'Solo la línea ' + n"
                                class="flex h-9 w-9 items-center justify-center rounded-sm font-titulo text-xl font-black leading-none"
                                :class="filtro === n ? 'outline outline-[3px] outline-offset-2 outline-texto' : ''"
                                :style="'background:var(--linea-' + n + ');color:var(--sobre-linea)'" x-text="n"></button>
                    </template>
                </div>
            </div>

            <div class="mt-4 overflow-hidden border-[3px] border-texto">
                <svg x-ref="plano" viewBox="0 0 1000 640" class="block h-auto w-full" role="img" aria-label="Plano con los recorridos de las cinco líneas y la posición de los colectivos. Los que tienen problemas llevan su número."></svg>
            </div>

            <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Qué significa cada marca">
                <li class="flex items-center gap-2"><svg width="30" height="30" viewBox="-15 -15 30 30" aria-hidden="true"><circle r="6" fill="var(--apoyo)"/><circle r="12" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="4 4"/></svg> Demorado</li>
                <li class="flex items-center gap-2"><svg width="30" height="30" viewBox="-15 -15 30 30" aria-hidden="true"><circle r="6" fill="var(--apoyo)"/><circle r="12" fill="none" stroke="currentColor" stroke-width="5"/></svg> Con una falla</li>
                <li class="flex items-center gap-2"><svg width="30" height="30" viewBox="-15 -15 30 30" aria-hidden="true"><circle r="5" fill="var(--apoyo)"/><circle r="9" fill="none" stroke="currentColor" stroke-width="2.5"/><circle r="13" fill="none" stroke="currentColor" stroke-width="2.5"/></svg> Fuera de recorrido</li>
                <li class="flex items-center gap-2"><svg width="30" height="30" viewBox="-15 -15 30 30" aria-hidden="true"><circle r="6" fill="var(--apoyo)"/></svg> Sin problemas</li>
            </ul>
        </section>

        <section aria-labelledby="titulo-atencion" class="min-w-0">
            <h2 id="titulo-atencion" class="font-titulo text-[2.6rem] font-black leading-none">Necesitan atención</h2>
            <p class="mt-1 text-apoyo">Atendé un incidente para acortarlo, o resolvelo si ya está arreglado.</p>

            <div x-show="datos && datos.atencion.length === 0" x-cloak class="mt-4 border-[3px] border-dashed border-texto p-6 text-center">
                <x-icono nombre="en-hora" :tamano="44" class="mx-auto" />
                <p class="mt-3 font-titulo text-[1.9rem] font-extrabold leading-none">Todo en orden</p>
                <p class="mt-1 text-apoyo">Ningún colectivo tiene problemas en este momento.</p>
                <x-colectivos-linea class="mt-4 w-full text-apoyo/70" />
            </div>

            <ul class="mt-4 space-y-3" x-show="datos && datos.atencion.length > 0" x-cloak>
                <template x-for="c in (datos?.atencion ?? [])" :key="c.interno">
                    <li class="border-[3px] border-texto p-3.5" @mouseenter="resaltar(c.interno)" @mouseleave="soltar()" @focusin="resaltar(c.interno)" @focusout="soltar()">
                        {{-- En el celular el estado baja a su propio renglón y la ubicación se lee entera --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-sm font-titulo text-[1.9rem] font-black leading-none" :style="'background:var(--linea-' + c.linea + ');color:var(--sobre-linea)'" x-text="c.linea"></span>
                            <div class="min-w-0 grow basis-[calc(100%-3.5rem)] sm:basis-0">
                                <p class="font-titulo text-[1.8rem] font-extrabold leading-none">Colectivo <span x-text="c.interno"></span></p>
                                <p class="mt-1 text-sm text-apoyo sm:truncate" x-text="c.ubicacion"></p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold" :class="claseEstado(c.estado)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="square" aria-hidden="true"><use :href="'#i-' + iconoEstado(c.estado)"/></svg>
                                <span x-text="etiquetaEstado(c.estado)"></span>
                            </span>
                        </div>

                        <template x-if="c.incidente">
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t-2 border-dashed border-apoyo/50 pt-3">
                                <p class="text-sm"><span class="font-semibold" x-text="etiquetaTipo(c.tipo)"></span> desde hace <span class="font-semibold" x-text="c.minutos"></span> min<span x-show="c.incidente.atendido_por" class="text-apoyo">, lo atiende <span x-text="(c.incidente.atendido_por ?? '').split(' ')[0]"></span></span></p>
                                <span class="ml-auto flex items-center gap-2">
                                    <button type="button" x-show="c.incidente.puede_atender" @click="accionar(c.incidente, 'atender')" :disabled="enCurso === c.incidente.id"
                                            class="rounded-full border-2 border-texto px-3.5 py-1.5 text-sm font-semibold hover:bg-texto hover:text-fondo disabled:opacity-50">Atender</button>
                                    <button type="button" x-show="c.incidente.puede_resolver" @click="accionar(c.incidente, 'resolver')" :disabled="enCurso === c.incidente.id"
                                            class="rounded-full border-2 border-texto bg-senal px-3.5 py-1.5 text-sm font-semibold text-asfalto disabled:opacity-50">Resolver</button>
                                    <span x-show="c.incidente.resolviendo" class="text-sm text-apoyo">Resolviendo</span>
                                </span>
                                <p x-show="c.tipo === 'desvio'" class="w-full text-sm text-apoyo">Un desvío se cierra solo cuando el colectivo vuelve al recorrido.</p>
                            </div>
                        </template>
                    </li>
                </template>
            </ul>
        </section>
    </div>

    <div class="calzada-doble my-14" aria-hidden="true"></div>

    {{-- Historial del día --}}
    <section aria-labelledby="titulo-historial">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="titulo-historial" class="font-titulo text-[2.6rem] font-black leading-none">Incidentes de hoy</h2>
                <p class="mt-1 text-apoyo">Primero los que siguen abiertos, después los más recientes.</p>
            </div>
        </div>
        <p class="mt-4 text-apoyo" x-show="datos && datos.incidentes.length === 0" x-cloak>Todavía no hubo incidentes hoy.</p>

        <ul class="mt-4 grid gap-x-10 md:grid-cols-2" x-ref="lista" x-show="datos && datos.incidentes.length > 0" x-cloak>
            <template x-for="(i, orden) in (datos?.incidentes ?? [])" :key="i.id">
                <li :data-id="i.id" class="flex min-w-0 items-center gap-3 border-b-2 border-dashed border-apoyo/40 py-3" :class="[nuevos.has(i.id) ? 'entra-fila' : '', !todos && orden >= 8 ? 'max-md:hidden' : '']">
                    <span class="w-14 shrink-0 font-panel text-xl font-bold leading-none" x-text="i.hora"></span>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-sm font-titulo text-[1.4rem] font-black leading-none" :style="'background:var(--linea-' + i.linea + ');color:var(--sobre-linea)'" x-text="i.linea"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1.5 font-semibold">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true"><use :href="'#i-' + iconoTipo(i.tipo)"/></svg>
                            <span x-text="etiquetaTipo(i.tipo)"></span>
                        </span>
                        <span class="block text-sm text-apoyo">Colectivo <span x-text="i.interno"></span>, <span x-text="i.duracion_min"></span> min</span>
                    </span>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="claseIncidente(i.estado)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><use :href="'#i-' + iconoIncidente(i.estado)"/></svg>
                        <span x-text="etiquetaIncidente(i)"></span>
                    </span>
                </li>
            </template>
        </ul>

        {{-- Celular: la lista del día entero es muy larga, así que arranca con los primeros 8 --}}
        <button type="button" x-show="datos && datos.incidentes.length > 8" x-cloak @click="todos = !todos" :aria-expanded="todos"
                class="mt-5 w-full rounded-full border-2 border-texto px-4 py-2.5 font-semibold hover:bg-texto hover:text-fondo md:hidden"
                x-text="todos ? 'Mostrar solo los primeros 8' : 'Mostrar los ' + (datos?.incidentes.length ?? '') + ' incidentes de hoy'"></button>
    </section>

    <div class="calzada-doble my-14" aria-hidden="true"></div>

    {{-- Gráficos del día, en SVG propio --}}
    <h2 class="font-titulo text-[clamp(2.6rem,5vw,3.8rem)] font-black leading-none">El día en números</h2>
    <p class="mt-2 max-w-[60ch] text-apoyo">Incidentes desde la medianoche, hora de Paraná. Cada línea mantiene su color; el número va siempre al lado.</p>

    <div class="mt-8 grid gap-12 xl:grid-cols-[1.5fr_1fr]">
        <figure class="relative min-w-0">
            <div class="flex items-baseline justify-between gap-3">
                <figcaption class="font-titulo text-[1.8rem] font-extrabold leading-none">Incidentes por hora</figcaption>
                <button type="button" @click="tabla.hora = !tabla.hora" class="text-sm font-semibold underline underline-offset-4" x-text="tabla.hora ? 'Ver gráfico' : 'Ver como tabla'"></button>
            </div>

            <div x-show="!tabla.hora" class="relative mt-3" @mouseleave="tip = null">
                <div x-html="htmlHoras" @mouseover="alPasarSobre($event)"></div>
                <div x-show="tip" x-cloak class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap border-2 border-texto bg-fondo px-2.5 py-1 text-sm font-semibold" :style="tip ? 'left:clamp(6.5rem,' + tip.x + '%,calc(100% - 6.5rem));top:' + (tip.y / tip.alto * 100) + '%' : ''" x-text="tip?.texto"></div>
            </div>

            <div x-show="tabla.hora" x-cloak class="mt-3 overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <caption class="sr-only">Incidentes por hora</caption>
                    <thead><tr class="border-b-[3px] border-texto"><th scope="col" class="pb-1.5 pr-3 font-semibold">Hora</th><th scope="col" class="pb-1.5 text-right font-semibold">Incidentes</th></tr></thead>
                    <tbody>
                        <template x-for="b in graficoHoras.barras.filter((x) => x.hora <= (datos?.hora_actual ?? 23))" :key="'t' + b.hora">
                            <tr class="border-b border-dashed border-apoyo/50"><th scope="row" class="py-1 pr-3 font-normal" x-text="b.rotulo"></th><td class="py-1 text-right font-semibold" x-text="b.v"></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </figure>

        <div class="min-w-0 space-y-12">
            <figure>
                <div class="flex items-baseline justify-between gap-3">
                    <figcaption class="font-titulo text-[1.8rem] font-extrabold leading-none">Incidentes por línea</figcaption>
                    <button type="button" @click="tabla.linea = !tabla.linea" class="text-sm font-semibold underline underline-offset-4" x-text="tabla.linea ? 'Ver gráfico' : 'Ver como tabla'"></button>
                </div>
                <div x-show="!tabla.linea" class="mt-3"><div x-html="htmlLineas"></div></div>
                <div x-show="tabla.linea" x-cloak class="mt-3">
                    <table class="w-full border-collapse text-left text-sm">
                        <caption class="sr-only">Incidentes por línea</caption>
                        <thead><tr class="border-b-[3px] border-texto"><th scope="col" class="pb-1.5 pr-3 font-semibold">Línea</th><th scope="col" class="pb-1.5 text-right font-semibold">Incidentes</th></tr></thead>
                        <tbody><template x-for="f in graficoLineas" :key="'tl' + f.linea"><tr class="border-b border-dashed border-apoyo/50"><th scope="row" class="py-1 pr-3 font-normal" x-text="'Línea ' + f.linea"></th><td class="py-1 text-right font-semibold" x-text="f.cantidad"></td></tr></template></tbody>
                    </table>
                </div>
            </figure>

            <figure>
                <figcaption class="font-titulo text-[1.8rem] font-extrabold leading-none">Qué pasó y cuánto duró</figcaption>
                <ul class="mt-3 space-y-3">
                    <template x-for="t in (datos?.graficos.por_tipo ?? [])" :key="t.tipo">
                        <li class="flex items-center gap-3 border-b-2 border-dashed border-apoyo/40 pb-3">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true"><use :href="'#i-' + iconoTipo(t.tipo)"/></svg>
                            <span class="flex-1 font-semibold" x-text="etiquetaTipo(t.tipo)"></span>
                            <span class="text-right"><span class="font-titulo text-3xl font-black leading-none" x-text="t.cantidad"></span><span class="block text-xs text-apoyo" x-text="t.cantidad > 0 ? t.minutos_promedio + ' min en promedio' : 'ninguno hoy'"></span></span>
                        </li>
                    </template>
                </ul>
            </figure>
        </div>
    </div>
</main>
@endsection
