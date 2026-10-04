// Reproductor del historial (M8): con las fotos que guardó el servidor (una cada ~10 s) calcula dónde estaba cada
// colectivo en CUALQUIER instante, también entre dos fotos. Así, al arrastrar la línea de tiempo o al reproducir
// a 60 veces la velocidad, los colectivos se mueven de forma continua hacia adelante y hacia atrás.
//
// Módulo puro: no toca el DOM ni la red, así que se prueba con `node --test` (tests/js/reproductor.test.mjs).

const METROS_POR_GRADO = 111320;

/** Si entre dos fotos un colectivo "salta" más que esto, no es movimiento: cambió de sentido o volvió al servicio. */
const SALTO_MAXIMO_M = 400;

const arcoCorto = (desde, hasta) => ((((hasta - desde) % 360) + 540) % 360) - 180;

function metros([lon1, lat1], [lon2, lat2]) {
    const k = Math.cos((((lat1 + lat2) / 2) * Math.PI) / 180);
    return Math.hypot((lon2 - lon1) * k, lat2 - lat1) * METROS_POR_GRADO;
}

export class Reproductor {
    /**
     * @param {object} o
     * @param {string[]} o.estados  Nombre de cada número de estado, como lo manda /api/historial.
     * @param {number} o.paso       Segundos entre fotos.
     */
    constructor({ estados = [], paso = 10 } = {}) {
        this.estados = estados;
        this.paso = paso * 1000;
        this.fotos = []; // { t (ms), colectivos: Map(id -> {...}) }, ordenadas por t
    }

    /**
     * Suma fotos de /api/historial ({t en segundos, c: [[id, ramal, lon, lat, rumbo, estado], ...]}).
     * Se pueden agregar en cualquier orden y repetidas: se ordenan y se descartan las que ya estaban.
     */
    agregar(fotos) {
        const conocidas = new Set(this.fotos.map((f) => f.t));

        for (const foto of fotos) {
            const t = foto.t * 1000;
            if (conocidas.has(t)) continue;
            conocidas.add(t);

            const colectivos = new Map();
            for (const [id, ramal, lon, lat, rumbo, estado] of foto.c) {
                colectivos.set(id, { id, ramal, lon, lat, rumbo, estado: this.estados[estado] ?? String(estado) });
            }
            this.fotos.push({ t, colectivos });
        }

        this.fotos.sort((a, b) => a.t - b.t);
    }

    /** Primer instante con datos, en ms (o null). */
    get desde() {
        return this.fotos.length ? this.fotos[0].t : null;
    }

    /** Último instante con datos, en ms (o null). */
    get hasta() {
        return this.fotos.length ? this.fotos[this.fotos.length - 1].t : null;
    }

    /** Hasta dónde hay datos seguidos (sin huecos grandes) a partir de `t`, en ms. Si en `t` no hay, devuelve `t`. */
    cubiertoHasta(t) {
        const i = this.#indiceAnterior(t);
        if (i < 0) return t;

        let fin = this.fotos[i].t;
        for (let j = i + 1; j < this.fotos.length; j++) {
            if (this.fotos[j].t - this.fotos[j - 1].t > this.paso * 2.5) break;
            fin = this.fotos[j].t;
        }
        return Math.max(t, fin);
    }

    /** ¿Hay datos para mostrar en este instante? */
    cubre(t) {
        const i = this.#indiceAnterior(t);
        if (i < 0) return false;
        const a = this.fotos[i];
        const b = this.fotos[i + 1];
        if (b) return b.t - a.t <= this.paso * 2.5;
        return t - a.t <= this.paso * 1.5;
    }

    /**
     * Dónde estaba cada colectivo en el instante `t` (ms).
     * @returns {{id:number, ramal:number, lon:number, lat:number, rumbo:number, estado:string}[]}
     */
    en(t) {
        const i = this.#indiceAnterior(t);
        const a = i >= 0 ? this.fotos[i] : null;
        const b = this.fotos[i + 1] ?? null;

        if (!a) {
            // Antes de la primera foto: solo si está muy cerca.
            return this.fotos.length && this.fotos[0].t - t <= this.paso * 1.5 ? [...this.fotos[0].colectivos.values()] : [];
        }

        if (!b) return t - a.t <= this.paso * 1.5 ? [...a.colectivos.values()] : [];

        // Un hueco grande (el servidor estuvo apagado): no se inventa movimiento.
        if (b.t - a.t > this.paso * 2.5) {
            const masCerca = t - a.t <= b.t - t ? a : b;
            return Math.abs(t - masCerca.t) <= this.paso * 1.5 ? [...masCerca.colectivos.values()] : [];
        }

        const u = b.t === a.t ? 0 : (t - a.t) / (b.t - a.t);
        const salida = [];

        for (const [id, previo] of a.colectivos) {
            const siguiente = b.colectivos.get(id);

            if (!siguiente) {
                if (u < 0.5) salida.push({ ...previo }); // salió de servicio entre las dos fotos
                continue;
            }

            const salto = previo.ramal !== siguiente.ramal || metros([previo.lon, previo.lat], [siguiente.lon, siguiente.lat]) > SALTO_MAXIMO_M;
            if (salto) {
                salida.push({ ...(u < 0.5 ? previo : siguiente) });
                continue;
            }

            salida.push({
                id,
                ramal: previo.ramal,
                lon: previo.lon + (siguiente.lon - previo.lon) * u,
                lat: previo.lat + (siguiente.lat - previo.lat) * u,
                rumbo: previo.rumbo + arcoCorto(previo.rumbo, siguiente.rumbo) * u,
                estado: u < 0.5 ? previo.estado : siguiente.estado,
            });
        }

        for (const [id, siguiente] of b.colectivos) {
            if (!a.colectivos.has(id) && u >= 0.5) salida.push({ ...siguiente }); // entró en servicio entre las dos fotos
        }

        return salida;
    }

    /** Índice de la última foto con t <= `t` (o -1). Búsqueda binaria. */
    #indiceAnterior(t) {
        let bajo = 0;
        let alto = this.fotos.length - 1;
        let mejor = -1;

        while (bajo <= alto) {
            const medio = (bajo + alto) >> 1;
            if (this.fotos[medio].t <= t) {
                mejor = medio;
                bajo = medio + 1;
            } else {
                alto = medio - 1;
            }
        }

        return mejor;
    }
}
