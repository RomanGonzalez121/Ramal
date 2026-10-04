// Calcula UNA SOLA VEZ, por fuera de la aplicación, un camino alternativo para cada tramo entre paradas de cada ramal.
// Un desvío manda al colectivo por otras calles entre dos paradas consecutivas y lo vuelve a poner en el recorrido
// en la parada siguiente. La aplicación no consulta ningún servicio de rutas mientras funciona: lee el archivo.
//
// Lee database/datos/lineas.json y recorridos.json, pregunta a OSRM (alternatives=true) y guarda
// database/datos/desvios.json. Misma política de uso que scripts/calcular-recorridos.mjs: máximo 1 pedido por
// segundo, User-Agent propio y atribución a OpenStreetMap (ODbL) y OSRM.
//
// Para cada tramo se prueba primero con las alternativas que ofrece OSRM y, como casi nunca las da en una ciudad
// de calles en cuadrícula, se fuerza el desvío pasando por un punto lateral a 350 y 600 m del tramo, de un lado
// y del otro; se queda con el desvío válido más corto.
//
// Una alternativa se acepta si:
//  - es entre 1,15 y 2,5 veces más larga que el tramo normal (si es igual, no es un desvío; si es enorme, es un disparate),
//  - y menos de la mitad de sus puntos están a menos de 25 m del tramo normal (si no, es casi el mismo camino).
//
// Uso: node scripts/calcular-desvios.mjs

import { readFileSync, writeFileSync } from 'node:fs';

const definicion = JSON.parse(readFileSync('database/datos/lineas.json', 'utf8'));
const recorridos = JSON.parse(readFileSync('database/datos/recorridos.json', 'utf8'));
const AGENTE = 'Ramal-portfolio/0.1 (proyecto de portfolio; romanxeneise5@gmail.com)';
const pausa = (ms) => new Promise((r) => setTimeout(r, ms));

const R = 6371008.8;
const rad = (g) => (g * Math.PI) / 180;
const distancia = ([lon1, lat1], [lon2, lat2]) => {
    const h = Math.sin((rad(lat2) - rad(lat1)) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin((rad(lon2) - rad(lon1)) / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(Math.min(1, h)));
};

/** Distancia de un punto al punto más cercano de una lista de puntos (alcanza porque los trazados son densos). */
const distanciaMinima = (punto, lista) => Math.min(...lista.map((q) => distancia(punto, q)));

/** Metros acumulados hasta cada punto. */
const acumuladas = (puntos) => {
    const a = [0];
    for (let i = 1; i < puntos.length; i++) a.push(a[i - 1] + distancia(puntos[i - 1], puntos[i]));
    return a;
};

/** Índice del punto del trazado más cercano a una coordenada, buscando de `desde` en adelante. */
const indiceMasCercano = (puntos, coord, desde = 0) => {
    let mejor = desde;
    let mejorD = Infinity;
    for (let i = desde; i < puntos.length; i++) {
        const d = distancia(puntos[i], coord);
        if (d < mejorD) {
            mejorD = d;
            mejor = i;
        }
    }
    return mejor;
};

/** Punto a `metros` de distancia, perpendicular al sentido del tramo, hacia un lado (+1) o el otro (-1). */
const puntoLateral = (normal, metros, lado) => {
    const m = Math.floor(normal.length / 2);
    const a = normal[Math.max(0, m - 2)];
    const b = normal[Math.min(normal.length - 1, m + 2)];
    const lat0 = normal[m][1];
    const kx = 111320 * Math.cos(rad(lat0));
    const ky = 111320;
    const dx = (b[0] - a[0]) * kx;
    const dy = (b[1] - a[1]) * ky;
    const largo = Math.hypot(dx, dy) || 1;
    const px = (-dy / largo) * lado;
    const py = (dx / largo) * lado;
    return [normal[m][0] + (px * metros) / kx, normal[m][1] + (py * metros) / ky];
};

const salida = {
    fuente: 'OSRM (router.project-osrm.org), alternativas del perfil de auto, sobre datos de OpenStreetMap (ODbL)',
    calculado: new Date().toISOString().slice(0, 10),
    ramales: {},
};

let probados = 0; // pedidos hechos
let aceptados = 0;
let tramosTotales = 0;

for (const linea of definicion.lineas) {
    const sentidos = { ida: linea.ida, vuelta: [...linea.ida].reverse() };

    for (const [sentido, claves] of Object.entries(sentidos)) {
        const clave = `${linea.numero}-${sentido}`;
        const trazado = recorridos.ramales[clave].puntos;
        const paradas = claves.map((k) => [recorridos.paradas[k].longitud, recorridos.paradas[k].latitud]);
        salida.ramales[clave] = {};

        let desde = 0;
        for (let k = 0; k < paradas.length - 1; k++) {
            tramosTotales++;
            const i0 = indiceMasCercano(trazado, paradas[k], desde);
            const i1 = indiceMasCercano(trazado, paradas[k + 1], i0);
            desde = i1;
            const normal = trazado.slice(i0, i1 + 1);
            const largoNormal = acumuladas(normal).at(-1);

            const url = `https://router.project-osrm.org/route/v1/driving/${paradas[k].join(',')};${paradas[k + 1].join(',')}?overview=full&geometries=geojson&alternatives=3&steps=false`;
            const respuesta = await fetch(url, { headers: { 'User-Agent': AGENTE } });
            const datos = await respuesta.json();
            await pausa(1200);
            probados++;

            if (datos.code !== 'Ok') {
                console.log(`${clave} tramo ${k + 1}: ${datos.code}`);
                continue;
            }

            const valida = (c) => {
                const razon = c.largo / largoNormal;
                const cercanos = c.puntos.filter((p) => distanciaMinima(p, normal) < 25).length / c.puntos.length;
                return razon >= 1.15 && razon <= 2.5 && cercanos < 0.5;
            };
            const aCandidata = (r) => ({ puntos: r.geometry.coordinates.map(([lon, lat]) => [+lon.toFixed(6), +lat.toFixed(6)]), largo: r.distance });

            let candidatas = datos.routes.map(aCandidata).filter(valida);

            // Casi nunca hay alternativas: se obliga a pasar por un punto a un costado del tramo.
            if (candidatas.length === 0) {
                for (const lateral of [350, 600]) {
                    for (const lado of [1, -1]) {
                        const via = puntoLateral(normal, lateral, lado);
                        const urlVia = `https://router.project-osrm.org/route/v1/driving/${paradas[k].join(',')};${via.join(',')};${paradas[k + 1].join(',')}?overview=full&geometries=geojson&steps=false`;
                        const r = await fetch(urlVia, { headers: { 'User-Agent': AGENTE } }).then((x) => x.json());
                        await pausa(1200);
                        probados++;
                        if (r.code === 'Ok') {
                            const c = aCandidata(r.routes[0]);
                            if (valida(c)) candidatas.push(c);
                        }
                    }
                    if (candidatas.length > 0) break;
                }
                candidatas.sort((x, y) => x.largo - y.largo);
            }

            if (candidatas.length === 0) {
                console.log(`${clave} tramo ${k + 1}: sin desvío válido (normal ${Math.round(largoNormal)} m)`);
                continue;
            }

            const elegida = candidatas[0];
            salida.ramales[clave][k + 1] = { largo_m: Math.round(elegida.largo), puntos: elegida.puntos };
            aceptados++;
            console.log(`${clave} tramo ${k + 1}: normal ${Math.round(largoNormal)} m, desvío ${Math.round(elegida.largo)} m`);
        }
    }
}

writeFileSync('database/datos/desvios.json', JSON.stringify(salida));
console.log(`\n${aceptados} desvíos aceptados de ${probados} tramos. Guardado database/datos/desvios.json`);
