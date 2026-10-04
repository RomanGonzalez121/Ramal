import assert from 'node:assert/strict';
import { mock, test } from 'node:test';
import { Celdas } from '../../resources/js/mapa/celdas.js';
import { TiempoReal } from '../../resources/js/mapa/tiempo-real.js';

const celdas = new Celdas({ caja: [-60.62, -31.82, -60.42, -31.66], columnas: 4, filas: 4 });
const VISTA_CHICA = [-60.515, -31.738, -60.505, -31.72]; // celda 2.1
const VISTA_OTRA = [-60.61, -31.7, -60.6, -31.68]; // celda 0.0

/** Un Echo de mentira que deja ver a qué canales está suscripto y permite simular cortes. */
function echoFalso(estadoInicial = 'connecting') {
    const canales = new Map();
    let escucha = null;
    const echo = {
        canales,
        connector: { pusher: { connection: { state: estadoInicial, bind: (_, cb) => (escucha = cb) } } },
        channel(nombre) {
            const oyentes = {};
            canales.set(nombre, oyentes);
            return { listen: (evento, cb) => { oyentes[evento] = cb; return this; } };
        },
        leave: (nombre) => canales.delete(nombre),
        cambiarA(estado) { echo.connector.pusher.connection.state = estado; escucha({ current: estado }); },
    };
    return echo;
}

function armar(opciones = {}) {
    const echo = opciones.sinEcho ? null : echoFalso(opciones.estado);
    const registro = { mensajes: [], estados: [], pedidos: 0 };
    const tr = new TiempoReal({
        celdas,
        crearEcho: () => echo,
        pedir: async () => {
            registro.pedidos++;
            if (opciones.fallaRed) throw new Error('sin red');
            return { tick: 9, colectivos: [{ id: 1 }] };
        },
        alMensaje: (c, tick, origen) => registro.mensajes.push({ c, tick, origen }),
        alEstado: (e) => registro.estados.push(e),
        ...opciones.parametros,
    });
    return { tr, echo, registro };
}

const vaciarPromesas = () => new Promise((r) => setImmediate(r));

test('se suscribe solo a los canales de las celdas que se ven', () => {
    const { tr, echo } = armar();
    tr.iniciar(VISTA_CHICA);
    assert.deepEqual([...echo.canales.keys()], ['posiciones.2.1']);
    tr.detener();
});

test('al mover el mapa deja los canales que ya no se ven y toma los nuevos', () => {
    const { tr, echo } = armar();
    tr.iniciar(VISTA_CHICA);
    tr.establecerVista(VISTA_OTRA);
    assert.deepEqual([...echo.canales.keys()], ['posiciones.0.0']);
    tr.detener();
});

test('un mensaje del canal llega a quien escucha', () => {
    const { tr, echo, registro } = armar();
    tr.iniciar(VISTA_CHICA);
    echo.canales.get('posiciones.2.1')['.actualizacion']({ tick: 4, colectivos: [{ id: 7 }] });
    assert.deepEqual(registro.mensajes, [{ c: [{ id: 7 }], tick: 4, origen: 'websocket' }]);
    tr.detener();
});

test('al conectar pasa a en vivo y pide una foto del estado', async () => {
    const { tr, echo, registro } = armar();
    tr.iniciar(VISTA_CHICA);
    echo.cambiarA('connected');
    await vaciarPromesas();
    assert.deepEqual(registro.estados, ['conectando', 'en-vivo']);
    assert.equal(registro.pedidos, 1);
    assert.equal(registro.mensajes[0].origen, 'foto');
    tr.detener();
});

test('si el WebSocket no conecta en unos segundos pasa a consultar cada pocos segundos', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    try {
        const { tr, registro } = armar({ parametros: { esperaSondeoMs: 5000, intervaloSondeoMs: 2000 } });
        tr.iniciar(VISTA_CHICA);

        mock.timers.tick(4999);
        assert.deepEqual(registro.estados, ['conectando']);

        mock.timers.tick(1);
        await vaciarPromesas();
        assert.deepEqual(registro.estados, ['conectando', 'sondeo']);
        assert.equal(registro.pedidos, 1);

        mock.timers.tick(2000);
        await vaciarPromesas();
        mock.timers.tick(2000);
        await vaciarPromesas();
        assert.equal(registro.pedidos, 3);
        tr.detener();
    } finally {
        mock.timers.reset();
    }
});

test('cuando el WebSocket vuelve, deja de consultar y vuelve a estar en vivo', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    try {
        const { tr, echo, registro } = armar();
        tr.iniciar(VISTA_CHICA);
        mock.timers.tick(5000);
        await vaciarPromesas();
        assert.equal(registro.estados.at(-1), 'sondeo');

        echo.cambiarA('connected');
        await vaciarPromesas();
        assert.equal(registro.estados.at(-1), 'en-vivo');

        const pedidos = registro.pedidos;
        mock.timers.tick(10000);
        await vaciarPromesas();
        assert.equal(registro.pedidos, pedidos, 'No sigue consultando');
        tr.detener();
    } finally {
        mock.timers.reset();
    }
});

test('un corte breve que se recupera antes del plazo nunca llega a consultar', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    try {
        const { tr, echo, registro } = armar({ estado: 'connected' });
        tr.iniciar(VISTA_CHICA);
        await vaciarPromesas();
        const antes = registro.pedidos;

        echo.cambiarA('unavailable');
        mock.timers.tick(3000);
        echo.cambiarA('connected');
        mock.timers.tick(10000);
        await vaciarPromesas();

        assert.ok(!registro.estados.includes('sondeo'));
        assert.equal(registro.pedidos, antes + 1, 'Solo la foto al reconectar');
        tr.detener();
    } finally {
        mock.timers.reset();
    }
});

test('sin WebSocket configurado va directo a consultar', async () => {
    const { tr, registro } = armar({ sinEcho: true });
    tr.iniciar(VISTA_CHICA);
    await vaciarPromesas();
    assert.equal(registro.estados[0], 'sondeo');
    assert.equal(registro.pedidos, 1);
    tr.detener();
});

test('si la consulta falla avisa que no hay conexión y se recupera cuando vuelve la red', async () => {
    mock.timers.enable({ apis: ['setTimeout', 'setInterval'] });
    try {
        let falla = true;
        const registro = { estados: [], mensajes: [] };
        const tr = new TiempoReal({
            celdas,
            crearEcho: () => null,
            pedir: async () => { if (falla) throw new Error('sin red'); return { tick: 1, colectivos: [] }; },
            alMensaje: (c, t, o) => registro.mensajes.push(o),
            alEstado: (e) => registro.estados.push(e),
        });
        tr.iniciar(VISTA_CHICA);
        await vaciarPromesas();
        assert.equal(registro.estados.at(-1), 'sin-conexion');

        falla = false;
        mock.timers.tick(2000);
        await vaciarPromesas();
        assert.equal(registro.estados.at(-1), 'sondeo');
        tr.detener();
    } finally {
        mock.timers.reset();
    }
});

test('detener libera los canales y corta los temporizadores', async () => {
    const { tr, echo, registro } = armar();
    tr.iniciar(VISTA_CHICA);
    tr.detener();
    assert.equal(echo.canales.size, 0);
    await vaciarPromesas();
    assert.equal(registro.pedidos, 0);
});
