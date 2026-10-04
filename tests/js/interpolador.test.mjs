import assert from 'node:assert/strict';
import { test } from 'node:test';
import { Interpolador, arcoCorto } from '../../resources/js/interpolador.js';

const cerca = (a, b, margen = 0.01) => assert.ok(Math.abs(a - b) <= margen, `${a} no está cerca de ${b}`);

test('el primer dato fija la posición sin animar', () => {
    const i = new Interpolador();
    i.actualizar('a', [{ x: 10, y: 20 }], 0);
    const p = i.posicion('a', 0);
    assert.equal(p.x, 10);
    assert.equal(p.y, 20);
});

test('a mitad del intervalo está a mitad del trazado', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 40, y: 0 }], 0);
    const p = i.posicion('a', 1000);
    cerca(p.x, 20);
    cerca(p.y, 0);
});

test('sigue las esquinas del trazado en vez de cortar camino', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 30, y: 0 }, { x: 30, y: 30 }], 0);
    const p = i.posicion('a', 1500); // 75 % de 60 unidades = 45 → 15 hacia abajo después de la esquina
    cerca(p.x, 30);
    cerca(p.y, 15);
});

test('el rumbo apunta hacia donde avanza', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 0, y: 40 }], 0);
    const p = i.posicion('a', 500);
    cerca(p.rumbo, 90, 0.5); // hacia abajo en pantalla
});

test('el rumbo gira por el arco corto y no da la vuelta larga', () => {
    cerca(arcoCorto(350, 10), 20);
    cerca(arcoCorto(10, 350), -20);
    cerca(arcoCorto(0, 180), -180);
});

test('un dato nuevo no produce saltos: arranca desde donde se ve el colectivo', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 40, y: 0 }], 0);
    const antes = i.posicion('a', 1000);
    // El servidor corrige: dice que en realidad iba más adelante.
    i.actualizar('a', [{ x: 40, y: 0 }, { x: 80, y: 0 }], 1000);
    const despues = i.posicion('a', 1000);
    cerca(despues.x, antes.x);
    cerca(despues.y, antes.y);
});

test('una corrección grande dura más para no teletransportar', () => {
    const i = new Interpolador({ intervalo: 2000, velocidadMaxima: 100 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 1000, y: 0 }], 0);
    const p = i.posicion('a', 2000);
    assert.ok(p.x < 300, `en 2 s no puede haber avanzado ${p.x}`);
    const fin = i.posicion('a', 10000);
    cerca(fin.x, 1000);
});

test('un dato más viejo que el último se ignora', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0, 100);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 40, y: 0 }], 0, 200);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: -500, y: 0 }], 10, 150);
    const p = i.posicion('a', 2000);
    cerca(p.x, 40);
});

test('si el dato se atrasa, el colectivo espera donde está en lugar de volver', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 40, y: 0 }], 0);
    const p = i.posicion('a', 9000);
    cerca(p.x, 40);
});

test('con movimiento reducido salta directo a la posición nueva', () => {
    const i = new Interpolador({ saltar: true });
    i.actualizar('a', [{ x: 0, y: 0 }], 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 40, y: 0 }], 0);
    const p = i.posicion('a', 10);
    cerca(p.x, 40);
});

test('puntos repetidos no rompen el cálculo', () => {
    const i = new Interpolador({ intervalo: 2000 });
    i.actualizar('a', [{ x: 5, y: 5 }], 0);
    i.actualizar('a', [{ x: 5, y: 5 }, { x: 5, y: 5 }, { x: 5, y: 5 }], 0);
    const p = i.posicion('a', 500);
    assert.ok(Number.isFinite(p.x) && Number.isFinite(p.y) && Number.isFinite(p.rumbo));
});

test('un colectivo nuevo aparece mirando hacia el rumbo que informa el servidor', () => {
    const i = new Interpolador();
    i.actualizar('a', [{ x: 5, y: 5 }], 0, 0, 90);
    cerca(i.posicion('a', 0).rumbo, 90);
});

test('sin rumbo inicial y sin movimiento, el rumbo es cero', () => {
    const i = new Interpolador();
    i.actualizar('a', [{ x: 5, y: 5 }], 0);
    cerca(i.posicion('a', 0).rumbo, 0);
});

test('al llegar un movimiento, el rumbo informado se va corrigiendo hacia el real', () => {
    const i = new Interpolador({ intervalo: 2000, giro: 100 });
    i.actualizar('a', [{ x: 0, y: 0 }], 0, 0, 0);
    i.actualizar('a', [{ x: 0, y: 0 }, { x: 0, y: 40 }], 0, 1); // avanza hacia abajo: 90 grados
    i.posicion('a', 0);
    const luego = i.posicion('a', 100);
    assert.ok(luego.rumbo > 0 && luego.rumbo < 90);

    let rumbo = luego.rumbo;
    for (let t = 116; t <= 1900; t += 16) rumbo = i.posicion('a', t).rumbo; // cuadros a ~60 fps
    cerca(rumbo, 90, 1);
});
