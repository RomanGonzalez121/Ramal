import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Celdas } from '../../resources/js/mapa/celdas.js';

// Los mismos casos que tests/Unit/Simulacion/CeldasTest.php: si uno cambia, el otro también.
const celdas = new Celdas({ caja: [-60.62, -31.82, -60.42, -31.66], columnas: 4, filas: 4 });

test('cada punto cae en una celda', () => {
    assert.equal(celdas.de(-60.61, -31.67), '0.0');
    assert.equal(celdas.de(-60.43, -31.81), '3.3');
    assert.equal(celdas.de(-60.51, -31.73), '2.1');
});

test('un punto fuera de la caja se pega al borde', () => {
    assert.equal(celdas.de(-61.0, -30.0), '0.0');
    assert.equal(celdas.de(-59.0, -33.0), '3.3');
});

test('una vista chica toca pocas celdas y una grande las toca todas', () => {
    assert.deepEqual(celdas.queCruzan([-60.515, -31.738, -60.505, -31.72]), ['2.1']);
    assert.equal(celdas.queCruzan([-60.62, -31.82, -60.42, -31.66]).length, 16);
});

test('una vista que cruza el borde de dos celdas toca las dos', () => {
    const cruzadas = celdas.queCruzan([-60.53, -31.738, -60.48, -31.72]);
    assert.ok(cruzadas.includes('2.1') && cruzadas.includes('1.1'));
    assert.equal(cruzadas.length, 2);
});
