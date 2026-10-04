// Tiempo real del mapa: Laravel Reverb por WebSocket y, si no alcanza, consulta periódica.
//
// - Se suscribe solo a los canales de las celdas que se ven (el servidor no manda lo que nadie mira).
// - Si el WebSocket se corta, Pusher reintenta solo. Si pasan unos segundos sin conexión, se pasa a
//   consultar /api/posiciones cada pocos segundos, y se vuelve al WebSocket cuando reconecta.
// - Al conectar (o reconectar) se pide una foto del estado para no esperar el próximo mensaje.
//
// Estados que informa `alEstado`: 'conectando', 'en-vivo', 'sondeo', 'sin-conexion'.
//
// Recibe sus dependencias por parámetro para poder probarse en Node sin red (tests/js/tiempo-real.test.mjs).

export class TiempoReal {
    /**
     * @param {object} o
     * @param {import('./celdas.js').Celdas} o.celdas
     * @param {() => object|null} o.crearEcho   Devuelve el cliente de Laravel Echo, o null si no hay WebSocket configurado.
     * @param {(vista:number[]) => Promise<{tick:number, colectivos:object[]}>} o.pedir  Foto del estado por HTTP.
     * @param {(colectivos:object[], tick:number, origen:string) => void} o.alMensaje
     * @param {(estado:string) => void} o.alEstado
     */
    constructor({ celdas, crearEcho, pedir, alMensaje, alEstado, esperaSondeoMs = 5000, intervaloSondeoMs = 2000, forzarSondeo = false }) {
        this.celdas = celdas;
        this.crearEcho = crearEcho;
        this.pedir = pedir;
        this.alMensaje = alMensaje;
        this.alEstado = alEstado;
        this.esperaSondeoMs = esperaSondeoMs;
        this.intervaloSondeoMs = intervaloSondeoMs;
        this.forzarSondeo = forzarSondeo;

        this.echo = null;
        this.vista = null;
        this.suscriptas = new Set();
        this.estado = null;
        this.temporizadorEspera = null;
        this.temporizadorSondeo = null;
        this.activo = false;
    }

    iniciar(vista) {
        this.activo = true;
        this.vista = vista;

        this.echo = this.forzarSondeo ? null : this.crearEcho();
        if (!this.echo) {
            this.#empezarSondeo();
            return;
        }

        this.#informar('conectando');
        this.echo.connector.pusher.connection.bind('state_change', ({ current }) => this.#cambioDeConexion(current));
        this.#cambioDeConexion(this.echo.connector.pusher.connection.state);
        this.#sincronizarCanales();
    }

    establecerVista(vista) {
        this.vista = vista;
        if (this.echo) this.#sincronizarCanales();
        if (this.estado === 'sondeo') this.#consultar();
    }

    detener() {
        this.activo = false;
        clearTimeout(this.temporizadorEspera);
        clearInterval(this.temporizadorSondeo);
        for (const celda of this.suscriptas) this.echo?.leave(`posiciones.${celda}`);
        this.suscriptas.clear();
    }

    #cambioDeConexion(estadoPusher) {
        if (!this.activo) return;

        if (estadoPusher === 'connected') {
            clearTimeout(this.temporizadorEspera);
            this.temporizadorEspera = null;
            clearInterval(this.temporizadorSondeo);
            this.temporizadorSondeo = null;
            this.#informar('en-vivo');
            this.#consultar('foto'); // se pierden mensajes mientras no hay conexión: se pide el estado actual
            return;
        }

        if (this.estado === 'sondeo' || this.temporizadorEspera) return;

        this.#informar('conectando');
        this.temporizadorEspera = setTimeout(() => {
            this.temporizadorEspera = null;
            this.#empezarSondeo();
        }, this.esperaSondeoMs);
    }

    #sincronizarCanales() {
        const quiero = new Set(this.vista ? this.celdas.queCruzan(this.vista) : []);

        for (const celda of quiero) {
            if (this.suscriptas.has(celda)) continue;
            this.echo.channel(`posiciones.${celda}`).listen('.actualizacion', (datos) => {
                if (this.activo) this.alMensaje(datos.colectivos, datos.tick, 'websocket');
            });
            this.suscriptas.add(celda);
        }

        for (const celda of [...this.suscriptas]) {
            if (quiero.has(celda)) continue;
            this.echo.leave(`posiciones.${celda}`);
            this.suscriptas.delete(celda);
        }
    }

    #empezarSondeo() {
        if (!this.activo || this.temporizadorSondeo) return;
        this.#informar('sondeo');
        this.#consultar();
        this.temporizadorSondeo = setInterval(() => this.#consultar(), this.intervaloSondeoMs);
    }

    async #consultar(origen = 'sondeo') {
        try {
            const { colectivos, tick } = await this.pedir(this.vista);
            if (!this.activo) return;
            this.alMensaje(colectivos, tick, origen);
            if (this.estado === 'sin-conexion') this.#informar('sondeo');
        } catch {
            if (this.activo && this.estado === 'sondeo') this.#informar('sin-conexion');
        }
    }

    #informar(estado) {
        if (this.estado === estado) return;
        this.estado = estado;
        this.alEstado(estado);
    }
}

/** Crea el cliente real de Laravel Echo contra Reverb, o null si falta la configuración. */
export async function crearEchoReverb(env = import.meta.env) {
    if (!env.VITE_REVERB_APP_KEY) return null;

    const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);
    window.Pusher = Pusher;

    return new Echo({
        broadcaster: 'reverb',
        key: env.VITE_REVERB_APP_KEY,
        wsHost: env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: env.VITE_REVERB_PORT ?? 8080,
        wssPort: env.VITE_REVERB_PORT ?? 443,
        forceTLS: (env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
