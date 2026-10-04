import test from 'node:test';
import assert from 'node:assert/strict';
import { registrarComoFunciona } from '../../resources/js/como-funciona.js';

// Un Alpine de mentira que solo guarda las fábricas de componentes.
const fabricas = {};
registrarComoFunciona({ data: (nombre, fabrica) => (fabricas[nombre] = fabrica) });

const constantes = { parada_s: 16.5, terminal_s: 105, arreglo_s: 300, velocidad_minima_ms: 1.5, factor_desvio: 0.85 };

const calculadora = (cambios = {}) => Object.assign(fabricas.calculadoraLlegada(constantes), cambios);

test('suma andar y paradas del camino, igual que el Estimador de PHP', () => {
    // 1800 m a 20 km/h: 324 s andando + 1 parada en el medio (16,5 s)
    const c = calculadora({ distancia: 1800, velocidadKmh: 20 });

    assert.equal(c.paradasEnElMedio, 1);
    assert.ok(Math.abs(c.andar - 324) < 0.01);
    assert.ok(Math.abs(c.total - 340.5) < 0.01);
    assert.equal(c.rotulo, '05:41');
});

test('con menos de un minuto el rótulo dice Llegando', () => {
    assert.equal(calculadora({ distancia: 100, velocidadKmh: 20 }).rotulo, 'LLEGANDO');
});

test('una falla suma el tiempo típico de arreglo', () => {
    const sana = calculadora();
    const rota = calculadora({ falla: true });

    assert.ok(Math.abs(rota.total - sana.total - 300) < 0.01);
});

test('un desvío suma tiempo solo si el tramo cabe antes de la parada', () => {
    const base = calculadora({ distancia: 1800, velocidadKmh: 20 });
    const conDesvio = calculadora({ distancia: 1800, velocidadKmh: 20, desvio: true });
    const muyCerca = calculadora({ distancia: 400, velocidadKmh: 20, desvio: true });

    assert.ok(conDesvio.total > base.total);
    assert.equal(muyCerca.extraDesvio, 0);
});

test('nunca usa una velocidad menor que la mínima', () => {
    const c = calculadora({ velocidadKmh: 1 });

    assert.equal(c.velocidadMs, 1.5);
});

test('las partes de la barra suman el cien por ciento', () => {
    const c = calculadora({ distancia: 2700, velocidadKmh: 15, falla: true, desvio: true });
    const suma = c.tramos.reduce((a, t) => a + t.ancho, 0);

    assert.ok(Math.abs(suma - 100) < 0.001);
    assert.equal(c.tramos.length, 4);
});

test('mientras más lejos está el colectivo, más a la izquierda se dibuja', () => {
    const cerca = calculadora({ distancia: 500 }).dibujo.colectivo;
    const lejos = calculadora({ distancia: 3000 }).dibujo.colectivo;

    assert.ok(lejos < cerca);
    assert.ok(cerca < 96);
});

test('la grilla arranca con el centro y se puede cambiar celda por celda', () => {
    const g = Object.assign(fabricas.grillaDeCanales(4, 4), {});
    g.init();

    assert.deepEqual([...g.activas].sort((a, b) => a - b), [5, 6, 9, 10]);
    assert.equal(g.cantidad, 4);

    g.alternar(0);
    assert.equal(g.cantidad, 5);
    g.alternar(5);
    assert.equal(g.esActiva(5), false);

    g.vista('toda');
    assert.equal(g.cantidad, 16);
    g.vista('una');
    assert.equal(g.cantidad, 1);
});
