import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { crearEchoReverb } from '../../resources/js/mapa/tiempo-real.js';

// Un fallo al arrancar el JavaScript de la página se lleva puesto a Alpine: sin él, los menús aparecen abiertos y el mapa no carga.
// Pasó en el despliegue (sin clave de Reverb) por un Echo global creado al importar app.js.

test('app.js no crea un cliente de WebSockets al cargar', () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

    assert.doesNotMatch(app, /import\s+['"]\.\/echo['"]/);
    assert.doesNotMatch(app, /new Echo\(/);
});

test('sin clave de Reverb no hay cliente y el mapa usa la consulta periódica', async () => {
    assert.equal(await crearEchoReverb({}), null);
    assert.equal(await crearEchoReverb({ VITE_REVERB_APP_KEY: '' }), null);
});
