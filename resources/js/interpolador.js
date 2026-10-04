// Interpolador de posiciones.
//
// El servidor manda posiciones cada pocos segundos. El navegador dibuja a 60 fps
// con el tiempo como referencia (no con una cantidad fija de cuadros), siguiendo
// el trazado que recorrió el colectivo y girando hacia el rumbo con suavidad.
//
// Módulo puro: no toca el DOM, así que se prueba con `node --test`.

const distancia = (a, b) => Math.hypot(b.x - a.x, b.y - a.y);
const limitar = (v, min, max) => Math.min(max, Math.max(min, v));

/** Diferencia angular más corta, en grados, entre -180 y 180. */
export const arcoCorto = (desde, hasta) => ((((hasta - desde) % 360) + 540) % 360) - 180;

function construirTramo(puntos) {
    const limpios = [];
    for (const p of puntos) {
        const ultimo = limpios[limpios.length - 1];
        if (!ultimo || distancia(ultimo, p) > 1e-6) limpios.push({ x: p.x, y: p.y });
    }
    const acumulado = [0];
    for (let i = 1; i < limpios.length; i++) {
        acumulado.push(acumulado[i - 1] + distancia(limpios[i - 1], limpios[i]));
    }
    return { puntos: limpios, acumulado, largo: acumulado[acumulado.length - 1] ?? 0 };
}

function muestrear(tramo, recorrido) {
    const { puntos, acumulado } = tramo;
    if (puntos.length === 1) return { x: puntos[0].x, y: puntos[0].y, rumbo: null };

    let i = 1;
    while (i < puntos.length - 1 && acumulado[i] < recorrido) i++;

    const a = puntos[i - 1];
    const b = puntos[i];
    const largoTramo = acumulado[i] - acumulado[i - 1];
    const k = largoTramo === 0 ? 1 : limitar((recorrido - acumulado[i - 1]) / largoTramo, 0, 1);

    return {
        x: a.x + (b.x - a.x) * k,
        y: a.y + (b.y - a.y) * k,
        rumbo: (Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI,
    };
}

export class Interpolador {
    /**
     * @param {object} opciones
     * @param {number} opciones.intervalo       Milisegundos entre actualizaciones del servidor.
     * @param {number} opciones.velocidadMaxima Unidades por segundo; una corrección grande dura más para no teletransportar.
     * @param {number} opciones.giro            Constante de tiempo del giro, en ms.
     * @param {boolean} opciones.saltar         Con prefers-reduced-motion: salta a la posición nueva.
     */
    constructor({ intervalo = 2000, velocidadMaxima = 140, giro = 120, saltar = false } = {}) {
        this.intervalo = intervalo;
        this.velocidadMaxima = velocidadMaxima;
        this.giro = giro;
        this.saltar = saltar;
        this.colectivos = new Map();
    }

    get ids() {
        return [...this.colectivos.keys()];
    }

    /**
     * Llega un dato del servidor.
     * @param {string|number} id
     * @param {{x:number,y:number}[]} ruta  Trazado desde el último punto informado hasta el nuevo (incluye ambos).
     * @param {number} ahora                Tiempo en ms (performance.now()).
     * @param {number} [marca]              Marca de tiempo del dato; si es más vieja que la última, se ignora.
     * @param {number} [rumboInicial]       Rumbo (grados) con el que aparece un colectivo nuevo, antes de tener movimiento que lo indique.
     */
    actualizar(id, ruta, ahora, marca = ahora, rumboInicial = null) {
        if (!ruta || ruta.length === 0) return;
        const actual = this.colectivos.get(id);

        if (actual && marca < actual.marca) return; // dato viejo

        const destino = ruta[ruta.length - 1];

        if (!actual) {
            const tramo = construirTramo([destino]);
            this.colectivos.set(id, { tramo, inicio: ahora, duracion: 1, marca, rumbo: rumboInicial, ultimaConsulta: ahora, destino });
            return;
        }

        // Se parte de donde el colectivo se ve ahora, no de donde el servidor creía que estaba: sin saltos.
        const visible = this._muestra(actual, ahora);
        const tramo = construirTramo([{ x: visible.x, y: visible.y }, ...ruta]);
        const porDistancia = (tramo.largo / this.velocidadMaxima) * 1000;

        actual.tramo = tramo;
        actual.inicio = ahora;
        actual.duracion = Math.max(this.intervalo, porDistancia);
        actual.marca = marca;
        actual.destino = destino;
    }

    /** Posición y rumbo (grados, 0 = hacia la derecha) en el instante `ahora`. */
    posicion(id, ahora) {
        const c = this.colectivos.get(id);
        if (!c) return null;

        if (this.saltar) {
            const rumboSalto = this._rumboDeTramo(c);
            if (rumboSalto !== null) c.rumbo = rumboSalto;
            return { x: c.destino.x, y: c.destino.y, rumbo: c.rumbo ?? 0 };
        }

        const muestra = this._muestra(c, ahora);
        const objetivo = muestra.rumbo;

        if (objetivo !== null) {
            if (c.rumbo === null) {
                c.rumbo = objetivo;
            } else {
                const dt = limitar(ahora - c.ultimaConsulta, 0, 100);
                c.rumbo += arcoCorto(c.rumbo, objetivo) * (1 - Math.exp(-dt / this.giro));
            }
        }
        c.ultimaConsulta = ahora;

        return { x: muestra.x, y: muestra.y, rumbo: c.rumbo ?? 0 };
    }

    quitar(id) {
        this.colectivos.delete(id);
    }

    _muestra(c, ahora) {
        const avance = limitar((ahora - c.inicio) / c.duracion, 0, 1);
        return muestrear(c.tramo, avance * c.tramo.largo);
    }

    _rumboDeTramo(c) {
        const { puntos } = c.tramo;
        if (puntos.length < 2) return null;
        const a = puntos[puntos.length - 2];
        const b = puntos[puntos.length - 1];
        return (Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI;
    }
}
