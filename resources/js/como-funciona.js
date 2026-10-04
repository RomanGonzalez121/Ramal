/**
 * La página "Cómo funciona": la calculadora de llegada, el recorrido de un dato hasta el mapa y la grilla de canales.
 * Los números de la calculadora llegan del servidor (son las constantes del Estimador real), así no se desactualizan.
 */

const ESPACIADO_PARADAS_M = 900; // distancia típica entre paradas en los ramales de Ramal
const TRAMO_DESVIO_M = 600;
const LARGO_DESVIO = 1.4; // el camino alternativo es, en promedio, 1,4 veces más largo

export function registrarComoFunciona(Alpine) {
    /** Calcula lo mismo que el Estimador para el caso "la parada está por delante", pero con los controles a la vista. */
    Alpine.data('calculadoraLlegada', (k) => ({
        distancia: 1800, // metros que le faltan al colectivo
        velocidadKmh: 20, // velocidad media que viene teniendo
        falla: false,
        desvio: false,

        get velocidadMs() {
            return Math.max(k.velocidad_minima_ms, this.velocidadKmh / 3.6);
        },

        get paradasEnElMedio() {
            return Math.max(0, Math.ceil(this.distancia / ESPACIADO_PARADAS_M) - 1);
        },

        get andar() {
            return this.distancia / this.velocidadMs;
        },

        get esperas() {
            return this.paradasEnElMedio * k.parada_s;
        },

        get extraDesvio() {
            if (!this.desvio || this.distancia < TRAMO_DESVIO_M) return 0;
            const porElDesvio = (TRAMO_DESVIO_M * LARGO_DESVIO) / (k.factor_desvio * this.velocidadMs);
            return Math.max(0, porElDesvio - TRAMO_DESVIO_M / this.velocidadMs);
        },

        get extraFalla() {
            return this.falla ? k.arreglo_s : 0;
        },

        get total() {
            return this.andar + this.esperas + this.extraDesvio + this.extraFalla;
        },

        /** Lo que dice el rótulo: menos de un minuto es "Llegando"; si no, minutos y segundos. */
        get rotulo() {
            if (this.total < 60) return 'LLEGANDO';
            const m = Math.floor(this.total / 60);
            const s = Math.round(this.total % 60);
            return `${String(m).padStart(2, '0')}:${String(s === 60 ? 59 : s).padStart(2, '0')}`;
        },

        /** Los tramos de la barra, con su ancho en porciento, para dibujar de dónde sale cada segundo. */
        get tramos() {
            const partes = [
                { clave: 'andar', texto: 'Andar', segundos: this.andar },
                { clave: 'paradas', texto: `Paradas del camino (${this.paradasEnElMedio})`, segundos: this.esperas },
                { clave: 'desvio', texto: 'Desvío', segundos: this.extraDesvio },
                { clave: 'falla', texto: 'Arreglo de la falla', segundos: this.extraFalla },
            ].filter((p) => p.segundos > 0.5);

            return partes.map((p) => ({ ...p, ancho: (p.segundos / this.total) * 100 }));
        },

        /** Posiciones (en el dibujo, de 0 a 100) de las paradas del camino y del colectivo. */
        get dibujo() {
            const escala = 100 / 3600;
            // La parada está a la derecha (x = 96). Cuanto más lejos está el colectivo, más a la izquierda se dibuja.
            const colectivo = 96 - this.distancia * escala * 0.92;
            const paradas = [];
            for (let i = 1; i <= this.paradasEnElMedio; i++) {
                paradas.push(96 - i * ESPACIADO_PARADAS_M * escala * 0.92);
            }
            return { colectivo, paradas };
        },

        /** Las paradas del camino como texto SVG: Alpine no puede repetir elementos (`x-for`) adentro de un `<svg>`. */
        get paradasSvg() {
            return this.dibujo.paradas
                .map((x) => `<circle cx="${x.toFixed(2)}" cy="20" r="1.7" class="fill-asfalto stroke-papel" stroke-width="1"/>`)
                .join('');
        },

        segundosTexto(s) {
            if (s < 60) return `${Math.round(s)} s`;
            return `${Math.floor(s / 60)} min ${String(Math.round(s % 60)).padStart(2, '0')} s`;
        },
    }));

    /** Del simulador al mapa: con el WebSocket andando y con el plan B de consulta cada pocos segundos. */
    Alpine.data('recorridoDelDato', () => ({
        websocket: true,
        paso: -1, // qué nodo está resaltado al apretar los botones (-1: ninguno)

        alternar() {
            this.websocket = !this.websocket;
        },

        elegir(i) {
            this.paso = this.paso === i ? -1 : i;
        },
    }));

    /** La ciudad dividida en celdas: el servidor solo manda lo que cae en las celdas que se están mirando. */
    Alpine.data('grillaDeCanales', (columnas, filas) => ({
        columnas,
        filas,
        activas: new Set(),

        init() {
            this.vista('centro');
        },

        get total() {
            return this.columnas * this.filas;
        },

        get cantidad() {
            return this.activas.size;
        },

        esActiva(i) {
            return this.activas.has(i);
        },

        alternar(i) {
            const siguientes = new Set(this.activas);
            siguientes.has(i) ? siguientes.delete(i) : siguientes.add(i);
            this.activas = siguientes;
        },

        vista(nombre) {
            const celdas = new Set();
            const c = this.columnas;

            if (nombre === 'toda') {
                for (let i = 0; i < this.total; i++) celdas.add(i);
            } else if (nombre === 'centro') {
                // Un recuadro de 2x2 en el medio: lo que se ve con el mapa acercado al centro.
                const c0 = Math.floor(c / 2) - 1;
                const f0 = Math.floor(this.filas / 2) - 1;
                for (let f = f0; f < f0 + 2; f++) for (let col = c0; col < c0 + 2; col++) celdas.add(f * c + col);
            } else {
                celdas.add(Math.floor(this.filas / 2) * c + Math.floor(c / 2));
            }

            this.activas = celdas;
        },
    }));
}
