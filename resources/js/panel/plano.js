// Plano en vivo del centro de control (M6/M7): las cinco líneas y los colectivos moviéndose, con los que tienen
// problemas bien marcados. Es SVG propio, sin mapa de fondo: lo que importa acá es cuál colectivo necesita atención.
//
// Cada estado se distingue por la forma del anillo y por el número de interno, no solo por el color:
//   demorado            anillo punteado
//   con una falla       anillo grueso y lleno
//   fuera de recorrido  anillo doble

const NS = 'http://www.w3.org/2000/svg';
const ANCHO = 1000;
const MARGEN = 36;

const el = (etiqueta, atributos = {}, padre) => {
    const nodo = document.createElementNS(NS, etiqueta);
    for (const [k, v] of Object.entries(atributos)) nodo.setAttribute(k, v);
    padre?.appendChild(nodo);
    return nodo;
};

const PROBLEMAS = new Set(['demorado', 'averiado', 'fuera_de_recorrido']);

/**
 * @param {SVGSVGElement} svg
 * @param {{alElegir?: (interno: string) => void}} opciones
 */
export async function montarPlano(svg, { alElegir = () => {} } = {}) {
    const datos = await fetch('/api/mapa').then((r) => r.json());

    // Proyección plana corregida por la latitud (igual que /lineas).
    const puntos = datos.lineas.flatMap((l) => l.ramales.flatMap((r) => r.recorrido));
    const lons = puntos.map((p) => p[0]);
    const lats = puntos.map((p) => p[1]);
    const [minLon, maxLon, minLat, maxLat] = [Math.min(...lons), Math.max(...lons), Math.min(...lats), Math.max(...lats)];
    const k = Math.cos((((minLat + maxLat) / 2) * Math.PI) / 180);
    const escala = (ANCHO - 2 * MARGEN) / ((maxLon - minLon) * k);
    const alto = Math.round((maxLat - minLat) * escala + 2 * MARGEN);
    const x = (lon) => MARGEN + (lon - minLon) * k * escala;
    const y = (lat) => MARGEN + (maxLat - lat) * escala;

    svg.setAttribute('viewBox', `0 0 ${ANCHO} ${alto}`);
    svg.replaceChildren();

    el('rect', { width: ANCHO, height: alto, style: 'fill:var(--mapa-fondo)' }, svg);

    // Recorridos, de fondo y apagados.
    const capaRutas = el('g', { 'aria-hidden': 'true' }, svg);
    const rutas = new Map();
    for (const linea of datos.lineas) {
        const grupo = el('g', { style: 'transition: opacity 200ms cubic-bezier(0.23, 1, 0.32, 1)' }, capaRutas);
        for (const ramal of linea.ramales) {
            const d = ramal.recorrido.map((p, i) => `${i ? 'L' : 'M'}${x(p[0]).toFixed(1)} ${y(p[1]).toFixed(1)}`).join('');
            el('path', {
                d,
                fill: 'none',
                'stroke-linecap': 'round',
                'stroke-linejoin': 'round',
                style: `stroke:var(--linea-${linea.numero});stroke-width:4;opacity:${ramal.sentido === 'ida' ? 0.5 : 0.3}`,
                ...(ramal.sentido === 'vuelta' ? { 'stroke-dasharray': '2 8' } : {}),
            }, grupo);
        }
        rutas.set(linea.numero, grupo);
    }

    // Paradas: un círculo hueco, como en el plano de recorridos.
    const capaParadas = el('g', { 'aria-hidden': 'true' }, svg);
    for (const p of datos.paradas) {
        el('circle', { cx: x(p.longitud).toFixed(1), cy: y(p.latitud).toFixed(1), r: 5, style: 'fill:var(--fondo);stroke:var(--texto);stroke-width:2.5' }, capaParadas);
    }

    const capaColectivos = el('g', {}, svg);
    const nodos = new Map();
    let lineaFiltrada = null;
    let resaltado = null;

    function crear(c) {
        const g = el('g', {
            style: 'transition: transform 2.2s linear, opacity 200ms cubic-bezier(0.23, 1, 0.32, 1); cursor: pointer',
            tabindex: -1,
        }, capaColectivos);
        const anillos = el('g', {}, g);
        el('circle', { r: 8, style: `fill:var(--linea-${c.linea});stroke:var(--fondo);stroke-width:2.5` }, g);
        const etiqueta = el('text', { x: 14, y: 5, style: 'font: 800 15px "Familjen Grotesk", sans-serif; paint-order: stroke; stroke: var(--mapa-fondo); stroke-width: 4px; fill: var(--texto)' }, g);
        el('title', {}, g);
        g.addEventListener('click', () => alElegir(c.interno));
        const nodo = { g, anillos, etiqueta, c };
        nodos.set(c.id, nodo);
        return nodo;
    }

    const ETIQUETAS = { circulando: 'En camino', en_parada: 'En la parada', en_terminal: 'En la terminal', demorado: 'Demorado', averiado: 'Con una falla', fuera_de_recorrido: 'Fuera de recorrido' };

    function pintarEstado(nodo, c) {
        nodo.c = c;
        nodo.anillos.replaceChildren();
        if (c.estado === 'demorado') {
            el('circle', { r: 14, fill: 'none', style: 'stroke:var(--texto);stroke-width:3', 'stroke-dasharray': '4 4' }, nodo.anillos);
        } else if (c.estado === 'averiado') {
            el('circle', { r: 14, fill: 'none', style: 'stroke:var(--texto);stroke-width:5' }, nodo.anillos);
        } else if (c.estado === 'fuera_de_recorrido') {
            el('circle', { r: 14, fill: 'none', style: 'stroke:var(--texto);stroke-width:2.5' }, nodo.anillos);
            el('circle', { r: 19, fill: 'none', style: 'stroke:var(--texto);stroke-width:2.5' }, nodo.anillos);
        }
        // Solo los que tienen problemas llevan su número: así el plano se lee de un vistazo.
        nodo.etiqueta.textContent = PROBLEMAS.has(c.estado) ? c.interno : '';
        nodo.g.querySelector('title').textContent = `Colectivo ${c.interno}, línea ${c.linea}: ${ETIQUETAS[c.estado] ?? c.estado}`;
    }

    function aplicarFiltro() {
        for (const [n, grupo] of rutas) grupo.style.opacity = lineaFiltrada === null || lineaFiltrada === n ? 1 : 0.15;
        for (const nodo of nodos.values()) nodo.g.style.opacity = lineaFiltrada === null || lineaFiltrada === nodo.c.linea ? 1 : 0.2;
    }

    return {
        /** Recibe la lista de /api/posiciones. */
        actualizar(colectivos) {
            const vistos = new Set();
            for (const c of colectivos) {
                vistos.add(c.id);
                const [lon, lat] = c.ruta[c.ruta.length - 1];
                const nodo = nodos.get(c.id) ?? crear(c);
                nodo.g.style.transform = `translate(${x(lon).toFixed(1)}px, ${y(lat).toFixed(1)}px)`;
                pintarEstado(nodo, c);
            }
            for (const [id, nodo] of nodos) {
                if (!vistos.has(id)) {
                    nodo.g.remove();
                    nodos.delete(id);
                }
            }
            aplicarFiltro();
            if (resaltado) this.resaltar(resaltado);
        },

        filtrarLinea(n) {
            lineaFiltrada = n;
            aplicarFiltro();
        },

        /** Marca un colectivo (por ejemplo cuando se pasa el puntero por su fila en la lista). */
        resaltar(interno) {
            resaltado = interno;
            for (const nodo of nodos.values()) {
                const es = nodo.c.interno === interno;
                nodo.g.firstChild.style.display = es ? 'none' : '';
                nodo.g.querySelector('.resaltado')?.remove();
                if (es) {
                    el('circle', { class: 'resaltado', r: 24, fill: 'none', style: 'stroke:var(--senal);stroke-width:5' }, nodo.g);
                    nodo.g.parentNode.appendChild(nodo.g); // al frente
                    if (!nodo.etiqueta.textContent) nodo.etiqueta.textContent = nodo.c.interno;
                }
            }
        },

        quitarResaltado() {
            resaltado = null;
            for (const nodo of nodos.values()) {
                nodo.g.firstChild.style.display = '';
                nodo.g.querySelector('.resaltado')?.remove();
                nodo.etiqueta.textContent = PROBLEMAS.has(nodo.c.estado) ? nodo.c.interno : '';
            }
        },
    };
}
