import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Reproductor } from '../../resources/js/mapa/reproductor.js';

const ESTADOS = ['circulando', 'en_parada', 'en_terminal', 'demorado', 'averiado', 'fuera_de_recorrido', 'fuera_de_servicio'];
const cerca = (a, b, margen = 1e-6) => assert.ok(Math.abs(a - b) <= margen, `${a} no está cerca de ${b}`);

/** Una foto en el segundo `t` con los colectivos dados: [id, ramal, lon, lat, rumbo, estado]. */
const foto = (t, ...c) => ({ t, c });

const nuevo = () => new Reproductor({ estados: ESTADOS, paso: 10 });

test('en el instante exacto de una foto devuelve esa foto', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.49, -31.7, 0, 0])]);

    const [c] = r.en(100_000);
    assert.equal(c.id, 1);
    cerca(c.lon, -60.5);
});

test('entre dos fotos interpola la posición', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.499, -31.699, 0, 0])]);

    const [c] = r.en(105_000);
    cerca(c.lon, -60.4995);
    cerca(c.lat, -31.6995);
});

test('funciona igual hacia atrás: el orden en que se pide no importa', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.499, -31.7, 0, 0]), foto(120, [1, 1, -60.498, -31.7, 0, 0])]);

    const adelante = [102_000, 108_000, 115_000].map((t) => r.en(t)[0].lon);
    const atras = [115_000, 108_000, 102_000].map((t) => r.en(t)[0].lon);

    assert.deepEqual(atras, adelante.toReversed());
    assert.ok(adelante[0] < adelante[1] && adelante[1] < adelante[2], 'avanza de a poco');
});

test('el rumbo gira por el arco corto', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 170, 0]), foto(110, [1, 1, -60.5, -31.7, -170, 0])]);

    const [c] = r.en(105_000);
    cerca(Math.abs(c.rumbo), 180, 0.001);
});

test('el estado cambia a mitad de camino entre las dos fotos', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.5, -31.7, 0, 3])]);

    assert.equal(r.en(103_000)[0].estado, 'circulando');
    assert.equal(r.en(107_000)[0].estado, 'demorado');
});

test('si cambia de sentido en la terminal no se interpola entre los dos extremos', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.52, -31.74, 0, 2]), foto(110, [1, 2, -60.52, -31.74, 180, 0])]);

    assert.equal(r.en(103_000)[0].ramal, 1);
    assert.equal(r.en(107_000)[0].ramal, 2);
});

test('un salto de más de 400 m se trata como cambio, no como movimiento', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.4, -31.7, 0, 0])]);

    const antes = r.en(103_000)[0];
    const despues = r.en(107_000)[0];
    cerca(antes.lon, -60.5);
    cerca(despues.lon, -60.4);
});

test('un colectivo que sale de servicio desaparece a mitad de camino', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0], [2, 1, -60.4, -31.7, 0, 0]), foto(110, [1, 1, -60.5, -31.7, 0, 0])]);

    assert.equal(r.en(103_000).length, 2);
    assert.equal(r.en(107_000).length, 1);
});

test('un colectivo que entra en servicio aparece a mitad de camino', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.5, -31.7, 0, 0], [2, 1, -60.4, -31.7, 0, 0])]);

    assert.equal(r.en(103_000).length, 1);
    assert.equal(r.en(107_000).length, 2);
});

test('las fotos se pueden agregar en cualquier orden y repetidas', () => {
    const r = nuevo();
    r.agregar([foto(120, [1, 1, -60.48, -31.7, 0, 0]), foto(100, [1, 1, -60.5, -31.7, 0, 0])]);
    r.agregar([foto(110, [1, 1, -60.49, -31.7, 0, 0]), foto(100, [1, 1, -1, -1, 0, 0])]);

    assert.equal(r.fotos.length, 3);
    assert.equal(r.desde, 100_000);
    assert.equal(r.hasta, 120_000);
    cerca(r.en(100_000)[0].lon, -60.5, 1e-9);
});

test('sin datos devuelve una lista vacía y no rompe', () => {
    const r = nuevo();
    assert.deepEqual(r.en(5), []);
    assert.equal(r.desde, null);
    assert.equal(r.hasta, null);
    assert.equal(r.cubre(5), false);
});

test('antes de la primera foto o mucho después de la última no inventa posiciones', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(110, [1, 1, -60.5, -31.7, 0, 0])]);

    assert.deepEqual(r.en(50_000), []);
    assert.deepEqual(r.en(500_000), []);
    assert.equal(r.en(112_000).length, 1, 'Un poco después de la última, todavía se muestra');
});

test('un hueco grande entre fotos (servidor apagado) no se llena con movimiento inventado', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, -60.5, -31.7, 0, 0]), foto(1000, [1, 1, -60.4, -31.7, 0, 0])]);

    assert.deepEqual(r.en(500_000), []);
    assert.equal(r.cubre(500_000), false);
    cerca(r.en(101_000)[0].lon, -60.5);
    cerca(r.en(999_000)[0].lon, -60.4);
});

test('cubre dice si hay datos para ese instante', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, 0, 0, 0, 0]), foto(110, [1, 1, 0, 0, 0, 0]), foto(120, [1, 1, 0, 0, 0, 0])]);

    assert.equal(r.cubre(105_000), true);
    assert.equal(r.cubre(99_000), false);
    assert.equal(r.cubre(300_000), false);
});

test('cubiertoHasta cuenta hasta dónde llegan las fotos seguidas', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, 0, 0, 0, 0]), foto(110, [1, 1, 0, 0, 0, 0]), foto(120, [1, 1, 0, 0, 0, 0]), foto(500, [1, 1, 0, 0, 0, 0])]);

    assert.equal(r.cubiertoHasta(105_000), 120_000);
    assert.equal(r.cubiertoHasta(50_000), 50_000, 'Si en ese instante no hay datos, no avanza');
});

test('un estado con número desconocido no rompe: se muestra el número', () => {
    const r = nuevo();
    r.agregar([foto(100, [1, 1, 0, 0, 0, 99])]);

    assert.equal(r.en(100_000)[0].estado, '99');
});
