// Mapa público de Ramal (M4): MapLibre + PMTiles propio, colectivos que se deslizan, cartel de parada y selección de línea.
//
// La capa de colectivos y paradas es HTML/SVG sobre el mapa, no una capa del mapa: así cada colectivo puede
// girar suave con su rumbo y moverse a 60 fps con el interpolador, y las paradas son botones reales que se pueden
// tocar y alcanzar con el teclado.

import * as maplibregl from 'maplibre-gl';
import urlTrabajador from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from 'pmtiles';
import { Interpolador } from '../interpolador.js';
import { DURACION, EASE_OUT } from '../movimiento.js';
import { Celdas } from './celdas.js';
import { Reproductor } from './reproductor.js';
import { cambiarSeleccion, crearEstilo, desviosGeoJSON, recorridosGeoJSON } from './estilo.js';
import { crearEchoReverb, TiempoReal } from './tiempo-real.js';

const NS = 'http://www.w3.org/2000/svg';
const METROS_POR_GRADO = 111320;
const INTERVALO_MS = 2000;

const el = (etiqueta, atributos = {}, hijos = []) => {
    const nodo = document.createElementNS(NS, etiqueta);
    for (const [k, v] of Object.entries(atributos)) nodo.setAttribute(k, v);
    hijos.forEach((h) => nodo.appendChild(h));
    return nodo;
};

const prefiereMenosMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function temaActual() {
    const forzado = document.documentElement.dataset.theme;
    if (forzado === 'dark') return 'noche';
    if (forzado === 'light') return 'dia';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'noche' : 'dia';
}

/**
 * @param {object} o
 * @param {HTMLElement} o.contenedor  Donde va el mapa.
 * @param {HTMLElement} o.capa        Capa HTML encima del mapa (colectivos y paradas).
 * @param {SVGSVGElement} o.svgRuta   SVG encima del mapa para dibujar la línea elegida.
 * @param {object} o.ganchos          Funciones que avisan a la interfaz (Alpine) qué pasó.
 */
export async function crearMapa({ contenedor, capa, svgRuta, ganchos }) {
    const [datos, foto] = await Promise.all([
        fetch('/api/mapa').then((r) => r.json()),
        fetch('/api/posiciones').then((r) => r.json()),
    ]);

    const celdas = new Celdas(datos.celdas);
    const ramales = new Map(datos.lineas.flatMap((l) => l.ramales.map((r) => [r.id, { ...r, linea: l.numero }])));
    const lineas = new Map(datos.lineas.map((l) => [l.numero, l]));
    const paradasPorId = new Map(datos.paradas.map((p) => [p.id, p]));

    // Plano local para el interpolador: x al este, y al sur, en metros. Así el rumbo calculado es el de la pantalla.
    const cos0 = Math.cos((-31.74 * Math.PI) / 180);
    const aPlano = ([lon, lat]) => ({ x: lon * cos0 * METROS_POR_GRADO, y: -lat * METROS_POR_GRADO });
    const aGeo = ({ x, y }) => [x / (cos0 * METROS_POR_GRADO), -y / METROS_POR_GRADO];

    maplibregl.setWorkerUrl(urlTrabajador);
    const protocolo = new Protocol();
    maplibregl.addProtocol('pmtiles', protocolo.tile);

    const opcionesEstilo = {
        urlTeselas: new URL('/mapa/parana.pmtiles', window.location.origin).href,
        recorridos: recorridosGeoJSON(datos.lineas),
        glifos: `${window.location.origin}/mapa/fuentes/{fontstack}/{range}.pbf`,
        seleccion: null,
    };
    let tema = temaActual();

    const todos = datos.lineas.flatMap((l) => l.ramales.flatMap((r) => r.recorrido));
    const limites = new maplibregl.LngLatBounds();
    todos.forEach((p) => limites.extend(p));

    const mapa = new maplibregl.Map({
        container: contenedor,
        style: crearEstilo(tema, opcionesEstilo),
        bounds: limites,
        fitBoundsOptions: { padding: { top: 60, bottom: 60, left: window.innerWidth > 900 ? 400 : 30, right: 30 } },
        attributionControl: false,
        dragRotate: false,
        pitchWithRotate: false,
        touchPitch: false,
        maxZoom: 18,
        minZoom: 10.5,
        maxBounds: [[-60.75, -31.95], [-60.3, -31.55]],
    });
    mapa.on('error', (e) => console.error('Mapa:', e.error?.message ?? e));
    if (import.meta.env.DEV) window.__mapa = mapa;
    mapa.touchZoomRotate.disableRotation();
    mapa.keyboard.disableRotation();

    // ---------- colectivos ----------

    const interpolador = new Interpolador({ intervalo: INTERVALO_MS, velocidadMaxima: 20, saltar: prefiereMenosMovimiento() });
    // Si la persona cambia la preferencia con el mapa abierto, el movimiento se adapta sin recargar.
    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const alCambiarMovimiento = (e) => (interpolador.saltar = e.matches);
    menosMovimiento.addEventListener('change', alCambiarMovimiento);
    const buses = new Map(); // id -> { nodo, cuerpo, insignia, meta }
    let modo = 'vivo'; // 'vivo' o 'pasado' (M8: rebobinar)
    let lineaElegida = null;
    let colectivoElegido = null;

    function crearBus(c) {
        const cuerpo = el('svg', { viewBox: '-14 -7 28 14', width: 28, height: 14, class: 'bus-cuerpo', 'aria-hidden': 'true' }, [
            el('use', { href: '#colectivo-arriba', x: -12, y: -6, width: 24, height: 12 }),
        ]);
        const insignia = document.createElement('span');
        insignia.className = 'bus-insignia';
        insignia.hidden = true;

        const nodo = document.createElement('button');
        nodo.type = 'button';
        nodo.tabIndex = -1; // 40 paradas de teclado no ayudan; se elige por línea (M11 completa el teclado)
        nodo.className = 'bus-mapa';
        nodo.style.setProperty('--l', `var(--linea-${c.linea})`);
        nodo.append(cuerpo, insignia);
        nodo.addEventListener('click', () => elegirColectivo(c.id));
        capa.appendChild(nodo);

        const bus = { nodo, cuerpo, insignia, meta: c };
        buses.set(c.id, bus);
        return bus;
    }

    const ETIQUETAS = {
        circulando: 'En camino',
        en_parada: 'En la parada',
        en_terminal: 'En la terminal',
        demorado: 'Demorado',
        averiado: 'Con una falla',
        fuera_de_recorrido: 'Fuera de recorrido',
    };
    const INSIGNIAS = { demorado: 'demora', averiado: 'incidente', fuera_de_recorrido: 'fuera-de-recorrido' };

    function describir(c) {
        const ramal = ramales.get(c.ramal);
        return {
            id: c.id,
            interno: c.interno,
            linea: c.linea,
            estado: c.estado,
            etiqueta: ETIQUETAS[c.estado] ?? c.estado,
            destino: ramal?.destino ?? '',
            velocidadKmh: Math.round(c.velocidad * 3.6),
        };
    }

    function actualizarBus(bus, c, silencioso = false) {
        const previo = bus.meta.estado;
        bus.meta = c;
        bus.nodo.dataset.estado = c.estado;
        bus.nodo.setAttribute('aria-label', `Colectivo ${c.interno}, línea ${c.linea}, ${(ETIQUETAS[c.estado] ?? c.estado).toLowerCase()}`);

        const icono = INSIGNIAS[c.estado];
        bus.insignia.hidden = !icono;
        if (icono) {
            bus.insignia.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><use href="#i-${icono}"/></svg>`;
        }

        if (!silencioso && previo !== 'en_parada' && c.estado === 'en_parada') pulsarParadaCercana(bus);
        if (colectivoElegido === c.id) ganchos.alSeleccionarColectivo?.(describir(c));
    }

    function alMensaje(colectivos, tick, origen) {
        if (modo === 'pasado') return; // mientras se mira el pasado, el tiempo real queda en pausa
        const ahora = performance.now();

        for (const c of colectivos) {
            if (c.estado === 'fuera_de_servicio') {
                interpolador.quitar(c.id);
                buses.get(c.id)?.nodo.remove();
                buses.delete(c.id);
                continue;
            }

            const ruta = c.ruta.map(aPlano);
            const existe = interpolador.colectivos.has(c.id);
            const bus = buses.get(c.id) ?? crearBus(c);

            if (!existe) {
                interpolador.actualizar(c.id, ruta, ahora, ahora, c.rumbo);
            } else if (c.salto && origen === 'websocket') {
                // Cambió de sentido en la terminal o volvió al servicio: no hay camino que recorrer entre un punto y otro.
                interpolador.quitar(c.id);
                interpolador.actualizar(c.id, ruta, ahora, ahora, c.rumbo);
            } else if (c.salto) {
                // Foto o consulta periódica: llega solo un punto. Se dibuja el camino recto desde donde se lo vio por última vez.
                const previo = interpolador.colectivos.get(c.id).destino;
                const nuevo = ruta[ruta.length - 1];
                if (Math.hypot(nuevo.x - previo.x, nuevo.y - previo.y) > 400) {
                    interpolador.quitar(c.id);
                    interpolador.actualizar(c.id, ruta, ahora, ahora, c.rumbo);
                } else {
                    interpolador.actualizar(c.id, [previo, nuevo], ahora);
                }
            } else {
                interpolador.actualizar(c.id, ruta, ahora);
            }

            bus.nodo.hidden = false;
            actualizarBus(bus, c);
        }

        ganchos.alCantidad?.(buses.size);
        ultimoMensaje = ahora;
    }

    let ultimoMensaje = performance.now();
    alMensaje(foto.colectivos, foto.tick, 'foto');
    performance.mark('ramal:colectivos-visibles'); // para medir cuánto tarda en verse el primer colectivo

    // ---------- paradas ----------

    const paradas = new Map();
    for (const p of datos.paradas) {
        const nodo = document.createElement('button');
        nodo.type = 'button';
        nodo.className = 'parada-mapa';
        nodo.setAttribute('aria-label', `Parada ${p.nombre}, línea${p.lineas.length > 1 ? 's' : ''} ${p.lineas.join(', ')}`);
        nodo.innerHTML = '<svg viewBox="-12 -34 24 40" width="24" height="40" aria-hidden="true"><path d="M0 0V-14" class="poste"/><rect x="-9" y="-30" width="18" height="14" class="cartel"/><circle r="3.4" class="base"/></svg>';
        nodo.addEventListener('click', () => elegirParada(p.id));
        capa.appendChild(nodo);
        paradas.set(p.id, { ...p, nodo });
    }

    function pulsarParadaCercana(bus) {
        if (prefiereMenosMovimiento()) return;
        const pos = interpolador.posicion(bus.meta.id, performance.now());
        if (!pos) return;
        const [lon, lat] = aGeo(pos);

        for (const p of paradas.values()) {
            if (!p.lineas.includes(bus.meta.linea)) continue;
            const metros = Math.hypot((p.longitud - lon) * cos0, p.latitud - lat) * METROS_POR_GRADO;
            if (metros < 60) {
                const anillo = document.createElement('span');
                anillo.className = 'parada-pulso';
                p.nodo.appendChild(anillo);
                anillo.animate(
                    [{ transform: 'translate(-50%, 0) scale(.5)', opacity: 0.9 }, { transform: 'translate(-50%, 0) scale(2.4)', opacity: 0 }],
                    { duration: DURACION.pulsoParada, easing: EASE_OUT },
                ).onfinish = () => anillo.remove();
                return;
            }
        }
    }

    // ---------- línea elegida: el recorrido se dibuja de punta a punta ----------

    const trazos = new Map(); // sentido -> path
    let rutaSucia = true;

    function dibujarRuta(animar) {
        svgRuta.replaceChildren();
        trazos.clear();
        if (lineaElegida === null) return;

        for (const ramal of lineas.get(lineaElegida).ramales) {
            const path = el('path', {
                fill: 'none',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                pathLength: 1,
                style: `stroke:var(--linea-${lineaElegida});stroke-width:${ramal.sentido === 'ida' ? 7 : 4};opacity:${ramal.sentido === 'ida' ? 1 : 0.8}`,
            });
            if (ramal.sentido === 'vuelta') path.setAttribute('stroke-dasharray', '0.004 0.012');
            svgRuta.appendChild(path);
            trazos.set(ramal.sentido, { path, ramal, animar: animar && ramal.sentido === 'ida' });
        }
        rutaSucia = true;
    }

    function posicionarRuta() {
        for (const { path, ramal, animar } of trazos.values()) {
            path.setAttribute('d', ramal.recorrido.map((p, i) => {
                const { x, y } = mapa.project(p);
                return `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`;
            }).join(''));

            if (animar && !prefiereMenosMovimiento()) {
                const t = trazos.get(ramal.sentido);
                t.animar = false;
                path.animate(
                    [{ strokeDasharray: '1 1', strokeDashoffset: 1 }, { strokeDasharray: '1 1', strokeDashoffset: 0 }],
                    { duration: DURACION.dibujarLinea, easing: EASE_OUT },
                );
            }
        }
    }

    // ---------- bucle de dibujo: el tiempo manda, no la cantidad de cuadros ----------

    let activo = true;
    let cuadros = 0;
    let ultimoCuadro = performance.now();
    const duraciones = [];

    const escalaPorZoom = () => Math.min(2.2, Math.max(0.8, 0.75 + (mapa.getZoom() - 12) * 0.25));

    function pintar(ahora) {
        if (!activo) return;
        requestAnimationFrame(pintar);

        const delta = ahora - ultimoCuadro;
        duraciones.push(delta);
        if (duraciones.length > 120) duraciones.shift();
        ultimoCuadro = ahora;

        const escala = escalaPorZoom();

        if (modo === 'pasado') {
            avanzarPasado(delta, ahora);
            pintarPasado(escala);
        } else {
            for (const [id, bus] of buses) {
                const pos = interpolador.posicion(id, ahora);
                if (!pos) continue;
                const { x, y } = mapa.project(aGeo(pos));
                bus.nodo.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) scale(${escala.toFixed(2)})`;
                bus.cuerpo.style.transform = `rotate(${pos.rumbo.toFixed(1)}deg)`;
            }
        }

        if (rutaSucia) {
            for (const p of paradas.values()) {
                const { x, y } = mapa.project([p.longitud, p.latitud]);
                p.nodo.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) scale(${escala.toFixed(2)})`;
            }
            posicionarRuta();
            rutaSucia = false;
        }

        cuadros++;
        if (cuadros % 30 === 0) ganchos.alMedir?.(medir());
    }

    const medir = () => {
        const media = duraciones.reduce((a, b) => a + b, 0) / Math.max(1, duraciones.length);
        return { fps: Math.round(1000 / media), ms: +media.toFixed(1), colectivos: buses.size };
    };

    mapa.on('move', () => (rutaSucia = true));
    mapa.on('resize', () => (rutaSucia = true));
    requestAnimationFrame(pintar);

    // ---------- rebobinar: ver el día como un video (M8) ----------
    //
    // El servidor guarda una foto cada 10 s. Acá se piden por ventanas de 10 minutos y el Reproductor calcula dónde
    // estaba cada colectivo en cualquier instante entre dos fotos, así que arrastrar la línea de tiempo (en cualquier
    // sentido) o reproducir a 60x mueve a los colectivos de forma continua.

    const VENTANA_MS = 10 * 60 * 1000;
    const colectivosPorId = new Map(datos.colectivos.map((c) => [c.id, c]));
    const reproductor = new Reproductor({ paso: 10 });
    let tPasado = 0; // el instante que se está mostrando, en ms
    let reproduciendo = false;
    let velocidadPasado = 4;
    let rangoHistorial = null; // { desde, hasta } en ms
    const ventanas = new Map(); // número de ventana -> { promesa, pedidaEn }
    let ultimoAvisoTiempo = 0;
    let cargandoPasado = false;
    let sinRegistroAviso = false;

    async function pedirRango() {
        const r = await fetch('/api/historial/rango').then((x) => x.json());
        rangoHistorial = r.desde ? { desde: Date.parse(r.desde), hasta: Date.parse(r.hasta) } : null;
        if (r.paso_s) reproductor.paso = r.paso_s * 1000;
        ganchos.alRangoHistorial?.(rangoHistorial);
        return rangoHistorial;
    }

    /** Pide (una sola vez) las fotos de la ventana de 10 minutos número `k`. Las que llegan hasta ahora se vuelven a pedir. */
    function pedirVentana(k) {
        const inicio = k * VENTANA_MS;
        if (inicio > Date.now()) return Promise.resolve();

        const previa = ventanas.get(k);
        const abierta = (k + 1) * VENTANA_MS > Date.now() - 60000; // todavía le llegan fotos nuevas
        if (previa && (!abierta || Date.now() - previa.pedidaEn < 10000)) return previa.promesa;

        const url = `/api/historial?desde=${new Date(inicio - 30000).toISOString()}&hasta=${new Date(inicio + VENTANA_MS + 30000).toISOString()}`;
        const promesa = fetch(url)
            .then((r) => r.json())
            .then((d) => {
                reproductor.estados = d.estados;
                reproductor.agregar(d.fotos);
            })
            .catch(() => ventanas.delete(k));
        ventanas.set(k, { promesa, pedidaEn: Date.now() });
        return promesa;
    }

    /** Se asegura de tener las fotos del instante `t`, de la ventana siguiente (para que no se corte) y, si hace falta, de la anterior. */
    async function asegurarDatos(t) {
        const k = Math.floor(t / VENTANA_MS);
        const dentro = (t % VENTANA_MS) / VENTANA_MS;
        const pedidos = [pedirVentana(k)];
        if (dentro > 0.6 || velocidadPasado >= 16) pedidos.push(pedirVentana(k + 1));
        if (dentro < 0.1) pedidos.push(pedirVentana(k - 1));
        await Promise.all(pedidos);
    }

    function limitar(t) {
        return rangoHistorial ? Math.min(rangoHistorial.hasta, Math.max(rangoHistorial.desde, t)) : t;
    }

    function entrarAlPasado() {
        if (modo === 'pasado') return;
        modo = 'pasado';
        for (const id of buses.keys()) interpolador.quitar(id);
        for (const capaDesvio of ['desvios', 'desvios-borde']) {
            if (mapa.getLayer(capaDesvio)) mapa.setLayoutProperty(capaDesvio, 'visibility', 'none'); // los desvíos de ahora no son los de entonces
        }
        ganchos.alModo?.('pasado');
    }

    /** Muestra el instante `t` (ms). Si todavía no se había entrado al pasado, entra. */
    async function irA(t) {
        if (!rangoHistorial) await pedirRango();
        if (!rangoHistorial) {
            ganchos.alSinHistorial?.();
            return false;
        }

        entrarAlPasado();
        tPasado = limitar(t);
        ganchos.alTiempoPasado?.(tPasado);

        if (!reproductor.cubre(tPasado)) {
            cargandoPasado = true;
            ganchos.alCargandoHistorial?.(true);
            await asegurarDatos(tPasado);
            cargandoPasado = false;
            ganchos.alCargandoHistorial?.(false);
        } else {
            asegurarDatos(tPasado);
        }
        return true;
    }

    async function rebobinar(minutosAtras = 10) {
        await pedirRango();
        if (!rangoHistorial) {
            ganchos.alSinHistorial?.();
            return false;
        }
        reproduciendo = false;
        const ok = await irA(rangoHistorial.hasta - minutosAtras * 60000);
        if (ok) cambiarReproduccion(true);
        return ok;
    }

    function cambiarReproduccion(valor) {
        reproduciendo = valor;
        ganchos.alReproduciendo?.(valor);
    }

    async function volverAlVivo() {
        if (modo === 'vivo') return;
        modo = 'vivo';
        cambiarReproduccion(false);
        for (const capaDesvio of ['desvios', 'desvios-borde']) {
            if (mapa.getLayer(capaDesvio)) mapa.setLayoutProperty(capaDesvio, 'visibility', 'visible');
        }
        sinRegistroAviso = false;
        ganchos.alSinRegistro?.(false);
        ganchos.alModo?.('vivo');

        // Se vuelve a la foto actual: se parte de cero para que no queden colectivos del pasado.
        const actual = await fetch('/api/posiciones').then((r) => r.json());
        for (const id of buses.keys()) interpolador.quitar(id);
        alMensaje(actual.colectivos, actual.tick, 'foto');
        const vigentes = new Set(actual.colectivos.map((c) => c.id));
        for (const [id, bus] of buses) {
            if (!vigentes.has(id)) {
                bus.nodo.remove();
                buses.delete(id);
            }
        }
        ganchos.alCantidad?.(buses.size);
    }

    /** Hace avanzar el tiempo del pasado según la velocidad elegida, esperando si faltan datos. */
    function avanzarPasado(delta, ahora) {
        if (reproduciendo) {
            const siguiente = tPasado + delta * velocidadPasado;

            if (reproductor.cubre(siguiente) || reproductor.cubre(tPasado) === false) {
                tPasado = siguiente;
            } else {
                asegurarDatos(siguiente); // faltan fotos: se espera a que lleguen
            }

            if (rangoHistorial && tPasado >= rangoHistorial.hasta) {
                tPasado = rangoHistorial.hasta;
                const alDia = Date.now() - rangoHistorial.hasta < 40000;
                cambiarReproduccion(false);
                if (alDia) volverAlVivo();
            }

            // Antes de que se acaben las fotos de adelante, se piden las que siguen.
            if (reproductor.cubiertoHasta(tPasado) - tPasado < 120000 * Math.max(1, velocidadPasado / 4)) {
                asegurarDatos(tPasado + 120000 * Math.max(1, velocidadPasado / 4));
            }
        }

        if (ahora - ultimoAvisoTiempo > 100) {
            ultimoAvisoTiempo = ahora;
            ganchos.alTiempoPasado?.(tPasado);
        }
    }

    function pintarPasado(escala) {
        const estaban = reproductor.en(tPasado);
        const vistos = new Set();

        for (const c of estaban) {
            const meta = colectivosPorId.get(c.id);
            if (!meta) continue;
            vistos.add(c.id);

            const bus = buses.get(c.id) ?? crearBus({ ...meta, ramal: c.ramal, estado: c.estado, rumbo: c.rumbo, velocidad: 0 });
            bus.nodo.hidden = false;
            if (bus.meta.estado !== c.estado) actualizarBus(bus, { ...bus.meta, ramal: c.ramal, estado: c.estado, rumbo: c.rumbo }, true);

            const { x, y } = mapa.project([c.lon, c.lat]);
            bus.nodo.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) scale(${escala.toFixed(2)})`;
            bus.cuerpo.style.transform = `rotate(${c.rumbo.toFixed(1)}deg)`;
        }

        for (const [id, bus] of buses) {
            if (!vistos.has(id)) bus.nodo.hidden = true;
        }
        ganchos.alCantidad?.(vistos.size);
        const vacio = vistos.size === 0 && !cargandoPasado;
        if (vacio !== sinRegistroAviso) {
            sinRegistroAviso = vacio;
            ganchos.alSinRegistro?.(vacio);
        }
    }

    // Mientras se mira el pasado, el rango crece (llegan fotos nuevas): se actualiza para que la línea de tiempo llegue hasta ahora.
    const relojRango = setInterval(() => {
        if (modo === 'pasado') pedirRango();
    }, 30000);

    // ---------- desvíos en curso: el camino alternativo que están haciendo los colectivos fuera de recorrido ----------

    async function actualizarDesvios() {
        try {
            const { desvios } = await fetch('/api/desvios').then((r) => r.json());
            opcionesEstilo.desvios = desviosGeoJSON(desvios);
            mapa.getSource('desvios')?.setData(opcionesEstilo.desvios);
        } catch {
            /* sin los desvíos el mapa sigue funcionando */
        }
    }
    actualizarDesvios();
    const relojDesvios = setInterval(actualizarDesvios, 6000);

    // ---------- tiempo real ----------

    const margen = 0.12;
    const vistaActual = () => {
        const b = mapa.getBounds();
        const dx = (b.getEast() - b.getWest()) * margen;
        const dy = (b.getNorth() - b.getSouth()) * margen;
        return [b.getWest() - dx, b.getSouth() - dy, b.getEast() + dx, b.getNorth() + dy];
    };

    const echo = import.meta.env.VITE_TIEMPO_REAL === 'sondeo' ? null : await crearEchoReverb();
    const tiempoReal = new TiempoReal({
        celdas,
        crearEcho: () => echo,
        pedir: (vista) => fetch(`/api/posiciones?vista=${vista.join(',')}`).then((r) => r.json()),
        alMensaje,
        alEstado: (estado) => ganchos.alEstadoConexion?.(estado),
        forzarSondeo: !echo,
    });
    mapa.once('load', () => tiempoReal.iniciar(vistaActual()));
    mapa.on('moveend', () => tiempoReal.establecerVista(vistaActual()));

    // Si no llega nada durante un rato, la interfaz lo dice en vez de mostrar colectivos congelados.
    const vigilante = setInterval(() => ganchos.alSinNovedades?.(modo === 'vivo' && performance.now() - ultimoMensaje > 12000), 2000);

    // ---------- tema ----------

    function aplicarTema() {
        const nuevo = temaActual();
        if (nuevo === tema) return;
        tema = nuevo;
        mapa.setStyle(crearEstilo(tema, { ...opcionesEstilo, seleccion: lineaElegida }));
    }
    const observador = new MutationObserver(aplicarTema);
    observador.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    const medios = window.matchMedia('(prefers-color-scheme: dark)');
    medios.addEventListener('change', aplicarTema);

    // ---------- acciones que pide la interfaz ----------

    function elegirLinea(numero) {
        lineaElegida = numero === lineaElegida ? null : numero;
        cambiarSeleccion(mapa, lineaElegida);
        opcionesEstilo.seleccion = lineaElegida;

        for (const [, bus] of buses) {
            bus.nodo.classList.toggle('apagado', lineaElegida !== null && bus.meta.linea !== lineaElegida);
        }
        for (const p of paradas.values()) {
            p.nodo.classList.toggle('apagado', lineaElegida !== null && !p.lineas.includes(lineaElegida));
        }

        dibujarRuta(true);
        ganchos.alCambioLinea?.(lineaElegida);
    }

    function elegirParada(id) {
        const p = paradasPorId.get(id);
        for (const q of paradas.values()) q.nodo.classList.toggle('elegida', q.id === id);
        colectivoElegido = null;
        ganchos.alSeleccionarColectivo?.(null);
        ganchos.alSeleccionarParada?.(p ? { id: p.id, nombre: p.nombre, lineas: p.lineas } : null);

        if (p) {
            mapa.easeTo({
                center: [p.longitud, p.latitud],
                zoom: Math.max(mapa.getZoom(), 15.5),
                duration: prefiereMenosMovimiento() ? 0 : DURACION.camara,
                padding: { left: window.innerWidth > 900 ? 380 : 0, top: 0, right: 0, bottom: window.innerWidth > 900 ? 0 : 120 },
            });
        }
    }

    function elegirColectivo(id) {
        const bus = buses.get(id);
        colectivoElegido = bus ? id : null;
        for (const [otro, b] of buses) b.nodo.classList.toggle('elegido', otro === id);
        for (const q of paradas.values()) q.nodo.classList.remove('elegida');
        ganchos.alSeleccionarParada?.(null);
        ganchos.alSeleccionarColectivo?.(bus ? describir(bus.meta) : null);
    }

    return {
        elegirLinea,
        elegirParada,
        elegirColectivo,
        acercar: () => mapa.zoomIn({ duration: prefiereMenosMovimiento() ? 0 : DURACION.zoom }),
        alejar: () => mapa.zoomOut({ duration: prefiereMenosMovimiento() ? 0 : DURACION.zoom }),
        medir,
        rebobinar,
        irA,
        volverAlVivo,
        reproducir: () => cambiarReproduccion(true),
        pausar: () => cambiarReproduccion(false),
        velocidad(v) {
            velocidadPasado = v;
            ganchos.alVelocidad?.(v);
        },
        destruir() {
            activo = false;
            clearInterval(vigilante);
            clearInterval(relojRango);
            clearInterval(relojDesvios);
            observador.disconnect();
            medios.removeEventListener('change', aplicarTema);
            menosMovimiento.removeEventListener('change', alCambiarMovimiento);
            tiempoReal.detener();
            mapa.remove();
        },
    };
}
