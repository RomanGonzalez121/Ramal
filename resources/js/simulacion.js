// Simulación de demostración para /identidad (M0).
//
// "Servidor" de mentira: mueve 40 colectivos por 5 líneas sobre una grilla y emite
// posiciones cada 2 segundos, como lo hará el simulador real (M2) por Reverb (M3).
// El cliente las anima con el Interpolador. Es determinista: misma semilla, misma corrida.

import { Interpolador } from './interpolador.js';
import { DURACION, EASE_OUT } from './movimiento.js';

const NS = 'http://www.w3.org/2000/svg';
export const ANCHO = 1200;
export const ALTO = 700;
export const INTERVALO = 2000;

const LINEAS = [
    { id: 1, destino: 'Terminal', puntos: [[100, 100], [700, 100], [700, 300], [300, 300], [300, 500], [100, 500]], paradas: ['Plaza', 'Hospital', 'Terminal'] },
    { id: 2, destino: 'Costanera', puntos: [[1100, 100], [800, 100], [800, 400], [500, 400], [500, 600], [1100, 600]], paradas: ['Mercado', 'Estación', 'Costanera'] },
    { id: 3, destino: 'Parque', puntos: [[200, 200], [600, 200], [600, 500], [200, 500]], paradas: ['Escuela', 'Club', 'Parque'] },
    { id: 4, destino: 'Barrio Norte', puntos: [[900, 200], [1100, 200], [1100, 500], [900, 500]], paradas: ['Municipalidad', 'Biblioteca', 'Barrio Norte'] },
    { id: 5, destino: 'Cementerio', puntos: [[400, 100], [400, 200], [500, 200], [500, 300], [600, 300], [600, 100]], paradas: ['Correo', 'Banco', 'Cementerio'] },
];

const COLECTIVOS_POR_LINEA = 8;

function semilla(n) {
    let a = n >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Línea cerrada: cada vértice lleva su recorrido acumulado. */
function prepararLinea(def) {
    const pts = def.puntos.map(([x, y]) => ({ x, y }));
    const acum = [0];
    for (let i = 1; i <= pts.length; i++) {
        const a = pts[i - 1];
        const b = pts[i % pts.length];
        acum.push(acum[i - 1] + Math.hypot(b.x - a.x, b.y - a.y));
    }
    const largo = acum[acum.length - 1];
    const paradas = def.paradas.map((nombre, k) => ({
        id: `${def.id}-${k}`,
        linea: def.id,
        nombre,
        s: largo * [0.1, 0.43, 0.76][k],
    }));
    return { ...def, pts, acum, largo, paradas };
}

function puntoEn(linea, s) {
    const m = ((s % linea.largo) + linea.largo) % linea.largo;
    let i = 1;
    while (i < linea.acum.length - 1 && linea.acum[i] < m) i++;
    const a = linea.pts[i - 1];
    const b = linea.pts[i % linea.pts.length];
    const k = (m - linea.acum[i - 1]) / (linea.acum[i] - linea.acum[i - 1]);
    return { x: a.x + (b.x - a.x) * k, y: a.y + (b.y - a.y) * k };
}

/** Puntos por los que pasa el colectivo entre dos recorridos (con vueltas completas si hace falta). */
function tramo(linea, desde, hasta) {
    const salida = [puntoEn(linea, desde)];
    const vueltaDesde = Math.floor(desde / linea.largo);
    for (let v = vueltaDesde; v <= Math.floor(hasta / linea.largo); v++) {
        for (let i = 1; i < linea.acum.length - 1; i++) {
            const s = v * linea.largo + linea.acum[i];
            if (s > desde && s < hasta) salida.push({ x: linea.pts[i].x, y: linea.pts[i].y });
        }
    }
    salida.push(puntoEn(linea, hasta));
    return salida;
}

class Servidor {
    constructor(seed = 20260) {
        const rnd = semilla(seed);
        this.rnd = rnd;
        this.lineas = LINEAS.map(prepararLinea);
        this.colectivos = [];

        for (const linea of this.lineas) {
            for (let k = 0; k < COLECTIVOS_POR_LINEA; k++) {
                const s = (linea.largo / COLECTIVOS_POR_LINEA) * k + rnd() * 30;
                this.colectivos.push({
                    id: `${linea.id}-${k + 1}`,
                    linea,
                    s,
                    informado: s,
                    base: 26 + rnd() * 8,
                    fase: rnd() * Math.PI * 2,
                    espera: 0,
                    ultimaParada: null,
                });
            }
        }
    }

    avanzar(dt) {
        for (const c of this.colectivos) {
            c.fase += dt * (0.5 + this.rnd() * 0.3);
            const antes = c.s;
            let v = c.base * (1 + 0.28 * Math.sin(c.fase));

            if (c.espera > 0) {
                c.espera = Math.max(0, c.espera - dt);
                v = 0;
            }

            c.s += v * dt;

            for (const p of c.linea.paradas) {
                const a = antes % c.linea.largo;
                const b = c.s % c.linea.largo;
                const cruzo = a <= p.s && b >= p.s && b - a < 200;
                if (cruzo && c.ultimaParada !== p.id) {
                    c.espera = 1.2 + this.rnd() * 1.6;
                    c.ultimaParada = p.id;
                } else if (!cruzo && Math.abs(((c.s % c.linea.largo) - p.s)) > 40 && c.ultimaParada === p.id) {
                    c.ultimaParada = null;
                }
            }
        }
    }

    /** Lo que se manda por la red: el trazado recorrido desde el último informe. */
    emitir() {
        return this.colectivos.map((c) => {
            const ruta = tramo(c.linea, c.informado, c.s);
            c.informado = c.s;
            return { id: c.id, ruta };
        });
    }

    /** Segundos hasta que un colectivo llegue a la parada, con el mejor de la línea. */
    llegada(parada) {
        const linea = this.lineas.find((l) => l.id === parada.linea);
        let mejor = Infinity;
        for (const c of this.colectivos) {
            if (c.linea !== linea) continue;
            const falta = (((parada.s - c.s) % linea.largo) + linea.largo) % linea.largo;
            mejor = Math.min(mejor, falta / (c.base * 0.95));
        }
        return mejor;
    }
}

const el = (tag, attrs = {}, padre) => {
    const nodo = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) nodo.setAttribute(k, v);
    padre?.appendChild(nodo);
    return nodo;
};

function dibujarBase(svg) {
    const base = el('g', { 'aria-hidden': 'true' }, svg);
    el('rect', { width: ANCHO, height: ALTO, style: 'fill:var(--mapa-fondo)' }, base);
    el('rect', { x: 0, y: 640, width: ANCHO, height: 60, style: 'fill:var(--mapa-agua)' }, base);

    for (const [x, y, w, h] of [[110, 110, 80, 80], [610, 310, 80, 80], [910, 510, 80, 80], [310, 510, 80, 80]]) {
        el('rect', { x, y, width: w, height: h, rx: 4, style: 'fill:var(--mapa-parque)' }, base);
    }
    for (let x = 100; x <= 1100; x += 100) {
        el('path', { d: `M${x} 40V640`, style: 'stroke:var(--mapa-calle);stroke-width:18;fill:none' }, base);
    }
    for (let y = 100; y <= 600; y += 100) {
        el('path', { d: `M40 ${y}H1160`, style: 'stroke:var(--mapa-calle);stroke-width:18;fill:none' }, base);
    }
    return base;
}

export function montarMapa(svg, { reducirMovimiento = false, alLlegar = () => {}, alEstimar = () => {} } = {}) {
    const servidor = new Servidor();
    const interpolador = new Interpolador({ intervalo: INTERVALO, saltar: reducirMovimiento });

    svg.replaceChildren();
    dibujarBase(svg);

    // Recorridos: se dibujan de punta a punta al elegir una línea.
    const capaRutas = el('g', { 'aria-hidden': 'true' }, svg);
    const rutas = new Map();
    for (const linea of servidor.lineas) {
        const d = linea.pts.map((p, i) => `${i ? 'L' : 'M'}${p.x} ${p.y}`).join('') + 'Z';
        const path = el('path', {
            d,
            pathLength: 1,
            class: 'ruta-linea',
            style: `stroke:var(--linea-${linea.id});stroke-width:5;fill:none;stroke-linejoin:round;opacity:.55`,
        }, capaRutas);
        rutas.set(linea.id, path);
    }

    // Paradas: el cartel amarillo sobre un poste.
    const capaParadas = el('g', {}, svg);
    const paradas = new Map();
    for (const linea of servidor.lineas) {
        for (const p of linea.paradas) {
            const { x, y } = puntoEn(linea, p.s);
            const g = el('g', {
                transform: `translate(${x} ${y})`,
                tabindex: 0,
                role: 'button',
                'aria-label': `Parada ${p.nombre}, línea ${linea.id}`,
                'data-parada': p.id,
                style: 'cursor:pointer',
            }, capaParadas);
            el('circle', { r: 16, fill: 'transparent' }, g);
            el('path', { d: 'M0 0V-18', style: 'stroke:var(--texto);stroke-width:2.5;stroke-linecap:square' }, g);
            el('rect', { x: -8, y: -30, width: 16, height: 12, style: 'fill:var(--senal);stroke:var(--texto);stroke-width:2;transform-box:fill-box;transform-origin:center bottom;transition:transform 200ms var(--ease-out)' }, g);
            el('circle', { r: 3.4, style: 'fill:var(--fondo);stroke:var(--texto);stroke-width:2' }, g);
            paradas.set(p.id, { ...p, g, x, y, pulso: 0 });
        }
    }

    // Colectivos.
    const capaColectivos = el('g', {}, svg);
    const nodos = new Map();
    for (const c of servidor.colectivos) {
        const g = el('g', {
            class: 'colectivo-mapa',
            style: `--l:var(--linea-${c.linea.id});transform:translate(${ANCHO}px,${ALTO}px)`,
        }, capaColectivos);
        el('use', { href: '#colectivo-arriba', x: -14, y: -7, width: 28, height: 14 }, g);
        nodos.set(c.id, g);
    }

    let ahoraServidor = performance.now();
    const emitir = () => {
        const ahora = performance.now();
        servidor.avanzar((ahora - ahoraServidor) / 1000);
        ahoraServidor = ahora;
        if (cortadoHasta > ahora) return;
        for (const { id, ruta } of servidor.emitir()) interpolador.actualizar(id, ruta, ahora);
    };

    let cortadoHasta = 0;
    emitir();
    emitir();
    const reloj = setInterval(emitir, INTERVALO);

    // Estado de interacción.
    let lineaElegida = null;
    let paradaElegida = null;

    function pulsar(parada) {
        if (reducirMovimiento) return;
        const anillo = el('circle', {
            cx: parada.x,
            cy: parada.y - 24,
            r: 16,
            class: 'pulso-parada',
            style: 'fill:none;stroke:var(--senal);stroke-width:3;pointer-events:none',
        }, capaParadas);
        anillo.addEventListener('animationend', () => anillo.remove(), { once: true });
    }

    // Cuadro a cuadro: el tiempo es la referencia, no la cantidad de cuadros.
    let cuadro = 0;
    let ultimo = performance.now();
    const duraciones = [];
    let fps = 60;
    let ms = 16.7;
    let activo = true;

    function pintar(ahora) {
        if (!activo) return;
        duraciones.push(ahora - ultimo);
        if (duraciones.length > 60) duraciones.shift();
        ultimo = ahora;

        for (const c of servidor.colectivos) {
            const pos = interpolador.posicion(c.id, ahora);
            if (!pos) continue;
            nodos.get(c.id).style.transform = `translate(${pos.x.toFixed(2)}px,${pos.y.toFixed(2)}px) rotate(${pos.rumbo.toFixed(1)}deg)`;

            if (cuadro % 4 === 0) {
                for (const p of paradas.values()) {
                    if (p.linea !== c.linea.id || ahora < p.pulso) continue;
                    if (Math.hypot(p.x - pos.x, p.y - pos.y) < 7) {
                        p.pulso = ahora + 3000;
                        pulsar(p);
                        alLlegar(p, c);
                    }
                }
            }
        }

        if (cuadro % 30 === 0 && duraciones.length > 10) {
            const media = duraciones.reduce((a, b) => a + b, 0) / duraciones.length;
            ms = media;
            fps = 1000 / media;
        }
        cuadro++;
        requestAnimationFrame(pintar);
    }
    requestAnimationFrame(pintar);

    // Estimación de llegada: una vez por segundo.
    const estimador = setInterval(() => {
        if (!paradaElegida) return;
        alEstimar(paradaElegida, servidor.llegada(paradaElegida));
    }, 1000);

    function marcarLinea(id) {
        lineaElegida = id;
        for (const [n, path] of rutas) {
            const activa = id === null || n === id;
            path.style.opacity = id === null ? '.55' : activa ? '1' : '.12';
            path.style.strokeWidth = id !== null && activa ? '7' : '5';
        }
        for (const c of servidor.colectivos) {
            nodos.get(c.id).style.opacity = id === null || c.linea.id === id ? '1' : '.3';
        }
        if (id !== null && !reducirMovimiento) {
            rutas.get(id).animate(
                [{ strokeDasharray: '1 1', strokeDashoffset: 1 }, { strokeDasharray: '1 1', strokeDashoffset: 0 }],
                { duration: DURACION.dibujarLinea, easing: EASE_OUT },
            );
        }
    }

    function marcarParada(id, { resaltarLinea = true } = {}) {
        paradaElegida = paradas.get(id) ?? null;
        for (const p of paradas.values()) {
            p.g.querySelector('rect').style.strokeWidth = p === paradaElegida ? '4' : '2';
            p.g.querySelector('rect').style.transform = p === paradaElegida ? 'scale(1.25)' : 'scale(1)';
        }
        if (paradaElegida) {
            alEstimar(paradaElegida, servidor.llegada(paradaElegida));
            if (resaltarLinea) marcarLinea(paradaElegida.linea);
        }
    }

    for (const p of paradas.values()) {
        p.g.addEventListener('click', () => marcarParada(p.id));
        p.g.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                marcarParada(p.id);
            }
        });
    }

    return {
        servidor,
        lineas: servidor.lineas,
        paradas,
        get fps() { return fps; },
        get ms() { return ms; },
        get lineaElegida() { return lineaElegida; },
        get paradaElegida() { return paradaElegida; },
        marcarLinea,
        marcarParada,
        /** Simula una caída de datos y vuelve con una corrección grande. */
        cortarDatos(milisegundos = 6000) {
            cortadoHasta = performance.now() + milisegundos;
        },
        get datosCortados() { return cortadoHasta > performance.now(); },
        destruir() {
            activo = false;
            clearInterval(reloj);
            clearInterval(estimador);
        },
    };
}
