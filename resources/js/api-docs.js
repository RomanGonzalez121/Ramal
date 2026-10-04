/**
 * La página de documentación de la API (`/api`): el token que se pega para probar, el estado del servicio
 * y el probador de cada consulta, que pide de verdad a esta misma API.
 */

const PALABRAS = {
    200: ['en-hora', 'Bien'],
    401: ['candado', 'Sin token'],
    403: ['candado', 'Sin permiso'],
    404: ['sin-senal', 'No existe'],
    422: ['incidente', 'Dato inválido'],
    429: ['reloj', 'Demasiados pedidos'],
};

export function registrarDocumentacion(Alpine, { leer, guardar }) {
    /** Página completa: token, estado del servicio y cuál consulta está abierta. */
    Alpine.data('documentacionApi', () => ({
        token: leer('ramal-api-token') ?? '',
        verToken: false,
        abierto: null,
        estado: 'cargando', // cargando | activa | detenida | error
        colectivos: 0,
        base: '/api/v1',

        init() {
            this.base = `${location.origin}/api/v1`;
            this.$watch('token', (valor) => guardar('ramal-api-token', valor.trim()));

            // Si llegan con un enlace a una consulta (#listarColectivos), se abre sola.
            const ancla = decodeURIComponent(location.hash.slice(1));
            if (ancla && document.getElementById(ancla)?.tagName === 'ARTICLE') {
                this.abierto = ancla;
            }

            this.consultarEstado();
        },

        async consultarEstado() {
            try {
                const r = await fetch('/api/v1/estado', { headers: { Accept: 'application/json' } });
                const { data } = await r.json();
                this.colectivos = data.colectivos_en_servicio;
                this.estado = data.simulacion;
            } catch {
                this.estado = 'error';
            }
        },

        alternar(id) {
            this.abierto = this.abierto === id ? null : id;
        },

        abrir(id) {
            this.abierto = id;
        },
    }));

    /** Una consulta: arma la dirección con los valores escritos, la pide y muestra la respuesta tal cual. */
    Alpine.data('probador', (op) => ({
        requiereToken: op.requiereToken,
        // Solo lo obligatorio viene completo: un filtro opcional prellenado cambiaría la respuesta sin que se note.
        valores: Object.fromEntries(op.parametros.map((p) => [p.nombre, p.obligatorio ? (p.ejemplo ?? '') : ''])),
        cargando: false,
        respuesta: null,

        direccion() {
            let ruta = op.ruta;
            const consulta = new URLSearchParams();

            for (const p of op.parametros) {
                const valor = String(this.valores[p.nombre] ?? '').trim();
                if (p.en === 'path') {
                    ruta = ruta.replace(`{${p.nombre}}`, encodeURIComponent(valor || `{${p.nombre}}`));
                } else if (valor !== '') {
                    consulta.set(p.nombre, valor);
                }
            }

            const texto = consulta.toString();
            return `/api/v1${ruta}${texto ? `?${texto}` : ''}`;
        },

        async probar() {
            if (this.cargando) return;
            this.cargando = true;
            const t0 = performance.now();

            try {
                const cabeceras = { Accept: 'application/json' };
                if (this.requiereToken && this.token) cabeceras.Authorization = `Bearer ${this.token.trim()}`;

                const r = await fetch(this.direccion(), { headers: cabeceras });
                let cuerpo;
                if ((r.headers.get('Content-Type') ?? '').includes('protobuf')) {
                    const bytes = (await r.arrayBuffer()).byteLength;
                    cuerpo = `Respuesta binaria en protobuf: ${bytes} bytes.
Para verla como texto, escribí formato = json.`;
                } else {
                    const texto = await r.text();
                    cuerpo = texto;
                    try {
                        cuerpo = JSON.stringify(JSON.parse(texto), null, 2);
                    } catch {
                        /* no era JSON: se muestra tal cual */
                    }
                }

                const [icono, palabra] = PALABRAS[r.status] ?? ['alerta', 'Error'];
                const restantes = r.headers.get('X-RateLimit-Remaining');

                this.respuesta = {
                    ok: r.ok,
                    estado: r.status,
                    icono,
                    palabra,
                    ms: Math.round(performance.now() - t0),
                    restantes: restantes === null ? null : Number(restantes),
                    cuerpo: cuerpo.length > 6000 ? `${cuerpo.slice(0, 6000)}\n... (se cortó acá para que entre en pantalla)` : cuerpo,
                };
            } catch {
                this.respuesta = { ok: false, estado: 0, icono: 'sin-senal', palabra: 'Sin conexión', ms: Math.round(performance.now() - t0), restantes: null, cuerpo: 'No pudimos conectarnos con el servidor.' };
            } finally {
                this.cargando = false;
            }
        },
    }));
}
