import Alpine from 'alpinejs';
import { DURACION, EASE_OUT } from './movimiento.js';
import { montarMapa } from './simulacion.js';

window.Alpine = Alpine;

const reducirMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const leer = (clave) => {
    try {
        return localStorage.getItem(clave);
    } catch {
        return null;
    }
};
const guardar = (clave, valor) => {
    try {
        localStorage.setItem(clave, valor);
    } catch {
        /* sin almacenamiento: el tema igual cambia en esta visita */
    }
};

/** Interruptor Día/Noche. Por defecto sigue al sistema; elegir lo fija. */
Alpine.data('tema', () => ({
    actual: 'dia',

    init() {
        const guardado = leer('ramal-tema');
        const sistema = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'noche' : 'dia';
        this.actual = guardado ?? sistema;
        this.aplicar(this.actual);
    },

    aplicar(valor) {
        this.actual = valor;
        document.documentElement.dataset.theme = valor === 'noche' ? 'dark' : 'light';
    },

    alternar(evento) {
        const siguiente = this.actual === 'noche' ? 'dia' : 'noche';
        guardar('ramal-tema', siguiente);

        if (!document.startViewTransition || reducirMovimiento()) {
            this.aplicar(siguiente);
            return;
        }

        // Transición circular que nace en el interruptor.
        const caja = evento.currentTarget.getBoundingClientRect();
        const x = caja.left + caja.width / 2;
        const y = caja.top + caja.height / 2;
        const radio = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));

        const transicion = document.startViewTransition(() => this.aplicar(siguiente));
        transicion.ready.then(() => {
            document.documentElement.animate(
                { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${radio}px at ${x}px ${y}px)`] },
                { duration: DURACION.tema, easing: EASE_OUT, pseudoElement: '::view-transition-new(root)' },
            );
        });
    },
}));

/** Mapa de demostración con 40 colectivos y el panel de la parada elegida. */
Alpine.data('demoMapa', () => {
    let mapa = null;
    let temporizador = null;

    return {
        lineas: [1, 2, 3, 4, 5],
        linea: null,
        paradaNombre: '',
        paradaLinea: 3,
        destino: '',
        minutos: '--',
        llegando: false,
        parpadeo: false,
        fps: 60,
        ms: 16.7,
        cortado: false,

        init() {
            mapa = montarMapa(this.$refs.mapa, {
                reducirMovimiento: reducirMovimiento(),
                alEstimar: (parada, segundos) => this.estimar(parada, segundos),
            });
            this.elegirParada('3-1', { resaltarLinea: false });
            temporizador = setInterval(() => {
                this.fps = Math.round(mapa.fps);
                this.ms = mapa.ms.toFixed(1);
                this.cortado = mapa.datosCortados;
                this.linea = mapa.lineaElegida;
            }, 500);
        },

        destroy() {
            clearInterval(temporizador);
            mapa?.destruir();
        },

        estimar(parada, segundos) {
            this.paradaNombre = parada.nombre;
            this.paradaLinea = parada.linea;
            this.destino = mapa.lineas.find((l) => l.id === parada.linea).destino;
            const llegando = segundos < 60;
            const texto = llegando ? 'Llegando' : String(Math.ceil(segundos / 60)).padStart(2, '0');
            if (texto !== this.minutos && !reducirMovimiento()) {
                this.parpadeo = false;
                requestAnimationFrame(() => (this.parpadeo = true));
            }
            this.minutos = texto;
            this.llegando = llegando;
        },

        elegirLinea(n) {
            const nueva = this.linea === n ? null : n;
            mapa.marcarLinea(nueva);
            this.linea = nueva;
        },

        elegirParada(id, opciones) {
            mapa.marcarParada(id, opciones);
        },

        cortar() {
            mapa.cortarDatos(6000);
            this.cortado = true;
        },
    };
});


/** Mapa público: la interfaz es Alpine, el mapa vive en un módulo que se descarga solo en esta página. */
Alpine.data('mapaPublico', () => {
    let mapa = null;

    return {
        lineas: [1, 2, 3, 4, 5],
        linea: null,
        parada: null,
        colectivo: null,
        conexion: 'conectando',
        cantidad: 0,
        sinNovedades: false,
        medicion: { fps: 0, ms: 0 },
        cargando: true,
        error: false,

        // Llegadas a la parada elegida (M5)
        llegadas: [],
        llegadasEstado: 'vacio', // vacio, cargando, listo, error
        servicio: 'en_servicio',
        proximoServicio: null,
        calculadoEn: 0,
        reloj: 0,
        temporizadoresLlegadas: [],

        async init() {
            try {
                const { crearMapa } = await import('./mapa/pagina.js');
                mapa = await crearMapa({
                    contenedor: this.$refs.mapa,
                    capa: this.$refs.capa,
                    svgRuta: this.$refs.ruta,
                    ganchos: {
                        alEstadoConexion: (estado) => (this.conexion = estado),
                        alCantidad: (n) => (this.cantidad = n),
                        alCambioLinea: (n) => (this.linea = n),
                        alSeleccionarParada: (p) => {
                            this.parada = p;
                            this.seguirLlegadas(p);
                        },
                        alSeleccionarColectivo: (c) => (this.colectivo = c),
                        alSinNovedades: (v) => (this.sinNovedades = v),
                        alMedir: (m) => (this.medicion = m),
                    },
                });
                this.cargando = false;
            } catch (e) {
                console.error(e);
                this.cargando = false;
                this.error = true;
            }
        },

        destroy() {
            this.pararLlegadas();
            mapa?.destruir();
        },

        /** Pide las llegadas de la parada y las vuelve a pedir cada 6 s; entre pedido y pedido la cuenta baja sola. */
        seguirLlegadas(parada) {
            this.pararLlegadas();
            this.llegadas = [];
            this.servicio = 'en_servicio';

            if (!parada) {
                this.llegadasEstado = 'vacio';
                return;
            }

            this.llegadasEstado = 'cargando';
            const pedir = async () => {
                try {
                    const r = await fetch(`/api/paradas/${parada.id}/llegadas`);
                    if (!r.ok) throw new Error(r.status);
                    const datos = await r.json();
                    if (this.parada?.id !== parada.id) return;
                    this.llegadas = datos.llegadas;
                    this.servicio = datos.servicio;
                    this.proximoServicio = datos.proximo_servicio;
                    this.calculadoEn = Date.now();
                    this.llegadasEstado = 'listo';
                } catch {
                    if (this.llegadasEstado !== 'listo') this.llegadasEstado = 'error';
                }
            };

            pedir();
            this.temporizadoresLlegadas = [
                setInterval(pedir, 6000),
                setInterval(() => (this.reloj = Date.now()), 1000),
            ];
        },

        pararLlegadas() {
            this.temporizadoresLlegadas.forEach(clearInterval);
            this.temporizadoresLlegadas = [];
        },

        /** Las llegadas con la cuenta regresiva al segundo de ahora. */
        get filas() {
            void this.reloj; // para que se recalcule cada segundo
            const pasados = (Date.now() - this.calculadoEn) / 1000;

            return this.llegadas.map((l) => {
                const s = Math.max(0, l.segundos - pasados);
                const llegando = s < 60;
                const minutos = String(Math.ceil(s / 60)).padStart(2, '0');
                const texto = s <= 1 ? 'En parada' : llegando ? 'Llegando' : minutos;

                return {
                    ...l,
                    s,
                    llegando,
                    enParada: s <= 1,
                    minutos,
                    texto,
                    anuncio: `Línea ${l.linea} hacia ${l.destino}, ${s <= 1 ? 'en la parada' : llegando ? 'llegando' : `llega en ${Math.ceil(s / 60)} minutos`}`,
                };
            });
        },

        elegirLinea(n) { mapa?.elegirLinea(n); },
        cerrarParada() { mapa?.elegirParada(null); },
        cerrarColectivo() { mapa?.elegirColectivo(null); },
        acercar() { mapa?.acercar(); },
        alejar() { mapa?.alejar(); },
    };
});

/** Panel de operador (M6): indicadores, lista de atención, incidentes y gráficos del día en SVG propio. */
Alpine.data('panelOperador', () => {
    const TIPOS = {
        demora: { etiqueta: 'Demora', icono: 'demora' },
        desvio: { etiqueta: 'Desvío', icono: 'desvio' },
        falla: { etiqueta: 'Falla', icono: 'incidente' },
    };
    const ESTADOS = {
        demorado: { etiqueta: 'Demorado', icono: 'demora', clase: 'border-2 border-dashed border-texto' },
        averiado: { etiqueta: 'Con una falla', icono: 'incidente', clase: 'border-2 border-texto bg-texto text-fondo' },
        fuera_de_recorrido: { etiqueta: 'Fuera de recorrido', icono: 'fuera-de-recorrido', clase: 'border-4 border-double border-texto' },
    };
    const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

    let temporizador = null;
    let temporizadorPlano = null;
    let temporizadorReloj = null;
    let plano = null; // fuera del estado reactivo de Alpine: es un objeto con nodos del DOM

    return {
        reloj: '',
        filtro: null,
        datos: null,
        error: false,
        tabla: { hora: false, linea: false },
        tip: null,
        nuevos: new Set(),
        conocidos: null,
        htmlHoras: '',
        htmlLineas: '',
        animado: false,
        enCurso: null,
        aviso: '',
        temporizadorAviso: null,

        async init() {
            const marcarHora = () => (this.reloj = new Date().toLocaleTimeString('es-AR', { timeZone: 'America/Argentina/Buenos_Aires', hour12: false }));
            marcarHora();
            temporizadorReloj = setInterval(marcarHora, 1000);

            await this.actualizar();
            temporizador = setInterval(() => this.actualizar(), 5000);

            // El plano en vivo: se dibuja una vez y después solo se mueven los colectivos.
            try {
                const { montarPlano } = await import('./panel/plano.js');
                plano = await montarPlano(this.$refs.plano);
                const traerPosiciones = async () => {
                    try {
                        const { colectivos } = await fetch('/api/posiciones').then((r) => r.json());
                        plano.actualizar(colectivos);
                    } catch {
                        /* si falla una vez, se prueba de nuevo en el próximo turno */
                    }
                };
                await traerPosiciones();
                temporizadorPlano = setInterval(traerPosiciones, 2500);
            } catch (e) {
                console.error(e);
            }
        },

        destroy() {
            clearInterval(temporizador);
            clearInterval(temporizadorPlano);
            clearInterval(temporizadorReloj);
        },

        filtrar(n) {
            this.filtro = n;
            plano?.filtrarLinea(n);
        },

        resaltar(interno) {
            plano?.resaltar(interno);
        },

        soltar() {
            plano?.quitarResaltado();
        },

        get atencionCantidad() {
            return this.datos?.atencion.length ?? 0;
        },

        async actualizar() {
            try {
                const r = await fetch('/operador/resumen', { headers: { Accept: 'application/json' } });
                if (r.status === 401 || r.status === 403 || r.redirected) {
                    window.location.href = '/operador/ingresar';
                    return;
                }
                if (!r.ok) throw new Error(r.status);

                const nuevo = await r.json();
                const antes = this.posicionesLista();

                // Los incidentes que aparecen después de la primera carga entran con animación.
                const ids = new Set(nuevo.incidentes.map((i) => i.id));
                this.nuevos = this.conocidos ? new Set([...ids].filter((id) => !this.conocidos.has(id))) : new Set();
                this.conocidos = ids;

                this.datos = nuevo;
                this.error = false;
                this.redibujarGraficos();
                this.$nextTick(() => this.deslizarFilas(antes));
            } catch {
                this.error = true;
            }
        },

        /** Dónde está cada fila de la lista, para animar el desplazamiento cuando entra una nueva por arriba. */
        posicionesLista() {
            const lista = this.$refs.lista;
            if (!lista) return new Map();
            return new Map([...lista.querySelectorAll('li[data-id]')].map((li) => [li.dataset.id, li.getBoundingClientRect().top]));
        },

        deslizarFilas(antes) {
            if (reducirMovimiento() || antes.size === 0) return;
            this.$refs.lista?.querySelectorAll('li[data-id]').forEach((li) => {
                const previo = antes.get(li.dataset.id);
                if (previo === undefined) return;
                const diferencia = previo - li.getBoundingClientRect().top;
                if (Math.abs(diferencia) < 1) return;
                li.animate(
                    [{ transform: `translateY(${diferencia}px)` }, { transform: 'translateY(0)' }],
                    { duration: 250, easing: EASE_OUT },
                );
            });
        },

        /** Atender o resolver un incidente. El simulador aplica el cambio en su próximo tick, a los pocos segundos. */
        async accionar(incidente, accion) {
            this.enCurso = incidente.id;
            try {
                const r = await fetch(`/operador/incidentes/${incidente.id}/${accion}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });
                const datos = await r.json().catch(() => ({}));
                this.aviso = datos.mensaje ?? (r.ok ? '' : 'No se pudo hacer eso.');
                clearTimeout(this.temporizadorAviso);
                this.temporizadorAviso = setTimeout(() => (this.aviso = ''), 5000);
                await this.actualizar();
            } catch {
                this.aviso = 'No se pudo hacer eso. Revisá tu conexión.';
            } finally {
                this.enCurso = null;
            }
        },

        // ---- etiquetas ----
        etiquetaIncidente: (i) => (i.estado === 'resuelto' ? (i.resuelto_por === 'operador' ? 'Resuelto por operador' : 'Resuelto') : i.estado === 'atendido' ? `Atendido${i.atendido_por ? ' por ' + i.atendido_por.split(' ')[0] : ''}` : 'Abierto'),
        iconoIncidente: (estado) => ({ resuelto: 'en-hora', atendido: 'operador', activo: 'alerta' })[estado] ?? 'alerta',
        claseIncidente: (estado) => ({
            resuelto: 'border-2 border-apoyo text-apoyo',
            atendido: 'border-2 border-dashed border-texto',
            activo: 'border-2 border-texto bg-texto text-fondo',
        })[estado] ?? '',
        etiquetaTipo: (t) => TIPOS[t]?.etiqueta ?? t,
        iconoTipo: (t) => TIPOS[t]?.icono ?? 'incidente',
        etiquetaEstado: (e) => ESTADOS[e]?.etiqueta ?? e,
        iconoEstado: (e) => ESTADOS[e]?.icono ?? 'incidente',
        claseEstado: (e) => ESTADOS[e]?.clase ?? '',

        get horaActualizacion() {
            return this.datos ? new Date(this.datos.generado_en).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) : '';
        },

        get indicadores() {
            const i = this.datos?.indicadores ?? {};
            return [
                { clave: 'servicio', titulo: 'En servicio', icono: 'colectivo', valor: i.en_servicio ?? '-' },
                { clave: 'hora', titulo: 'Andan bien', icono: 'en-hora', valor: i.en_hora_pct === undefined ? '-' : `${i.en_hora_pct} %` },
                { clave: 'demorados', titulo: 'Demorados', icono: 'demora', valor: i.demorados ?? '-', alerta: i.demorados > 0 },
                { clave: 'averiados', titulo: 'Con falla', icono: 'incidente', valor: i.averiados ?? '-', alerta: i.averiados > 0 },
                { clave: 'fuera', titulo: 'Fuera de recorrido', icono: 'fuera-de-recorrido', valor: i.fuera_de_recorrido ?? '-', alerta: i.fuera_de_recorrido > 0 },
                { clave: 'abiertos', titulo: 'Incidentes abiertos', icono: 'alerta', valor: i.incidentes_activos ?? '-', alerta: false },
            ];
        },

        // ---- gráfico: incidentes por hora ----
        get graficoHoras() {
            const valores = this.datos?.graficos.por_hora ?? Array(24).fill(0);
            const actual = this.datos?.hora_actual ?? -1;
            const izq = 30, derecha = 636, arriba = 12, base = 200;
            const paso = (derecha - izq) / 24;
            const ancho = 14;

            const maximo = Math.max(...valores);
            const tope = maximo <= 4 ? 4 : Math.ceil(maximo / 2) * 2;
            const yDe = (v) => base - (v / tope) * (base - arriba);

            const barras = valores.map((v, hora) => {
                const x = izq + hora * paso + (paso - ancho) / 2;
                const y = yDe(v);
                const h = base - y;
                const r = Math.min(4, h);
                return {
                    hora, v, x, y, h,
                    centro: x + ancho / 2,
                    zonaX: izq + hora * paso,
                    zonaW: paso,
                    actual: hora === actual,
                    d: h > 0 ? `M${x} ${base}V${y + r}Q${x} ${y} ${x + r} ${y}H${x + ancho - r}Q${x + ancho} ${y} ${x + ancho} ${y + r}V${base}Z` : '',
                    rotulo: `${String(hora).padStart(2, '0')}:00 a ${String(hora).padStart(2, '0')}:59`,
                    texto: `${String(hora).padStart(2, '0')}:00 a ${String(hora).padStart(2, '0')}:59: ${plural(v, 'incidente', 'incidentes')}`,
                };
            });

            return {
                izq, arriba, base,
                barras,
                ejeY: [0, tope / 2, tope].map((valor) => ({ valor, y: yDe(valor) })),
                ejeX: [0, 3, 6, 9, 12, 15, 18, 21].map((hora) => ({ hora, texto: String(hora).padStart(2, '0'), x: izq + hora * paso + paso / 2 })),
            };
        },

        get resumenHoras() {
            const valores = this.datos?.graficos.por_hora ?? [];
            const total = valores.reduce((a, b) => a + b, 0);
            if (total === 0) return 'Todavía no hubo incidentes hoy.';
            const pico = valores.indexOf(Math.max(...valores));
            return `${plural(total, 'incidente', 'incidentes')} hoy. La hora con más fue a las ${String(pico).padStart(2, '0')}, con ${valores[pico]}.`;
        },

        // ---- gráfico: incidentes por línea ----
        get graficoLineas() {
            const filas = this.datos?.graficos.por_linea ?? [1, 2, 3, 4, 5].map((linea) => ({ linea, cantidad: 0 }));
            const maximo = Math.max(1, ...filas.map((f) => f.cantidad));
            return filas.map((f) => ({ ...f, ancho: (f.cantidad / maximo) * 300 }));
        },

        get resumenLineas() {
            return this.graficoLineas.map((f) => `Línea ${f.linea}, ${f.cantidad}`).join('. ');
        },

        // ---- los gráficos se dibujan como texto SVG: Alpine no puede repetir elementos (x-for) dentro de un <svg> ----

        /** Dibuja el SVG de incidentes por hora. Las barras crecen solo la primera vez que se dibujan. */
        svgHoras() {
            const g = this.graficoHoras;
            const crece = this.animado ? '' : 'barra-crece';
            const eje = g.ejeY.map((l) => `<line x1="${g.izq}" x2="640" y1="${l.y}" y2="${l.y}" stroke="currentColor" stroke-opacity="${l.valor === 0 ? 0.8 : 0.2}" ${l.valor === 0 ? '' : 'stroke-dasharray="4 5"'} stroke-width="1.5"/><text x="${g.izq - 8}" y="${l.y + 4}" text-anchor="end" font-size="12" fill="currentColor" fill-opacity="0.7">${l.valor}</text>`).join('');
            const barras = g.barras.filter((b) => b.h > 0).map((b) => `<path d="${b.d}" class="${crece}" style="--i:${b.hora}" fill="${b.actual ? 'var(--senal)' : 'currentColor'}" fill-opacity="${b.actual ? 1 : 0.85}" stroke="${b.actual ? 'currentColor' : 'none'}" stroke-width="2"/>`).join('');
            const horas = g.ejeX.map((t) => `<text x="${t.x}" y="${g.base + 18}" text-anchor="middle" font-size="12" fill="currentColor" fill-opacity="0.7">${t.texto}</text>`).join('');
            const zonas = g.barras.map((b) => `<rect x="${b.zonaX}" y="${g.arriba}" width="${b.zonaW}" height="${g.base - g.arriba}" fill="transparent" data-tip="${b.texto}" data-x="${(b.centro / 640) * 100}" data-y="${b.y - 6}" data-alto="230"/>`).join('');

            return `<svg viewBox="0 0 640 230" class="block h-auto w-full" role="img" aria-label="Incidentes por hora. ${this.resumenHoras}">${eje}${barras}${horas}${zonas}</svg>`;
        },

        /** Dibuja el SVG de incidentes por línea: el número de línea va siempre al lado, además del color. */
        svgLineas() {
            const crece = this.animado ? '' : 'barra-crece-x';
            const filas = this.graficoLineas.map((f, i) => {
                const barra = f.ancho > 0 ? `<path d="M44 8H${44 + f.ancho - 4}a4 4 0 0 1 4 4v6a4 4 0 0 1 -4 4H44z" class="${crece}" style="--i:${i}" fill="var(--linea-${f.linea})"/>` : '';
                return `<g transform="translate(0 ${i * 40 + 6})">
                    <rect width="30" height="30" rx="3" fill="var(--linea-${f.linea})"/>
                    <text x="15" y="23" text-anchor="middle" font-size="22" font-weight="900" style="font-family: 'Big Shoulders Display', sans-serif" fill="var(--sobre-linea)">${f.linea}</text>
                    ${barra}
                    <text x="${44 + f.ancho + 8}" y="20" font-size="15" font-weight="700" fill="currentColor">${f.cantidad}</text>
                                    </g>`;
            }).join('');

            return `<svg viewBox="0 0 400 210" class="block h-auto w-full" role="img" aria-label="Incidentes por línea. ${this.resumenLineas}">${filas}</svg>`;
        },

        /** Muestra el cartelito del gráfico sobre lo que está señalando el puntero. */
        alPasarSobre(evento) {
            const marca = evento.target.closest('[data-tip]');
            this.tip = marca
                ? { texto: marca.dataset.tip, x: Number(marca.dataset.x), y: Number(marca.dataset.y), alto: Number(marca.dataset.alto) }
                : null;
        },

        redibujarGraficos() {
            const horas = this.svgHoras();
            const lineas = this.svgLineas();
            if (horas !== this.htmlHoras) this.htmlHoras = horas;
            if (lineas !== this.htmlLineas) this.htmlLineas = lineas;
            this.animado = true;
        },
    };
});

Alpine.start();

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
