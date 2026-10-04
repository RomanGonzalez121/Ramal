#!/usr/bin/env node
/**
 * Prueba de carga simple: varios "visitantes" piden la foto de posiciones a la vez, como lo hace el mapa cuando
 * se cae al plan B de consulta cada 2 segundos.
 *
 * Uso: node scripts/prueba-de-carga.mjs [direccion] [visitantes] [pedidos por visitante]
 *   node scripts/prueba-de-carga.mjs http://127.0.0.1:8000 20 25
 *
 * Mide cuántos pedidos por segundo aguanta el servidor y cuánto tarda el 95 % de las respuestas.
 * Ojo: `php artisan serve` atiende de a un pedido; un servidor real (PHP-FPM, Apache) va a dar mejores números.
 */

const base = process.argv[2] ?? 'http://127.0.0.1:8000';
const visitantes = Number(process.argv[3] ?? 20);
const pedidos = Number(process.argv[4] ?? 25);
const rutas = ['/api/posiciones', '/api/mapa', '/api/paradas/1/llegadas'];

const tiempos = [];
let errores = 0;

async function visitante(i) {
    for (let n = 0; n < pedidos; n++) {
        const ruta = rutas[(i + n) % rutas.length];
        const t0 = performance.now();
        try {
            const r = await fetch(base + ruta);
            await r.arrayBuffer();
            if (!r.ok) errores++;
        } catch {
            errores++;
        }
        tiempos.push(performance.now() - t0);
    }
}

const inicio = performance.now();
await Promise.all(Array.from({ length: visitantes }, (_, i) => visitante(i)));
const total = (performance.now() - inicio) / 1000;

tiempos.sort((a, b) => a - b);
const pct = (p) => tiempos[Math.min(tiempos.length - 1, Math.floor(tiempos.length * p))].toFixed(0);

console.log(`${tiempos.length} pedidos de ${visitantes} visitantes en ${total.toFixed(1)} s`);
console.log(`Pedidos por segundo: ${(tiempos.length / total).toFixed(1)}`);
console.log(`Tiempo de respuesta: mediana ${pct(0.5)} ms, 95 % ${pct(0.95)} ms, máximo ${pct(1)} ms`);
console.log(`Errores: ${errores}`);
process.exitCode = errores > 0 ? 1 : 0;
