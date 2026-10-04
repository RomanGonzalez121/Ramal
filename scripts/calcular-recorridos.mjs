// Calcula UNA SOLA VEZ, por fuera de la aplicación, el camino por calles reales de cada ramal.
// Lee database/datos/lineas.json, pregunta a OSRM y guarda database/datos/recorridos.json.
// La aplicación no depende de ningún servicio de rutas cuando está funcionando: solo lee ese archivo.
//
// Política de uso del servidor de demostración de OSRM: uso razonable y no comercial, máximo
// 1 pedido por segundo, User-Agent propio y atribución a OpenStreetMap (ODbL) y OSRM.
//
// Uso: node scripts/calcular-recorridos.mjs

import { readFileSync, writeFileSync } from 'node:fs';

const definicion = JSON.parse(readFileSync('database/datos/lineas.json', 'utf8'));
const AGENTE = 'Ramal-portfolio/0.1 (proyecto de portfolio; romanxeneise5@gmail.com)';
const pausa = (ms) => new Promise((r) => setTimeout(r, ms));

const salida = {
    fuente: 'OSRM (router.project-osrm.org), perfil de auto, sobre datos de OpenStreetMap (ODbL)',
    calculado: new Date().toISOString().slice(0, 10),
    // Cada parada se apoya sobre la calle más cercana: así cae sobre el trazado, como una parada real.
    paradas: {},
    ramales: {},
};

for (const linea of definicion.lineas) {
    const sentidos = { ida: linea.ida, vuelta: [...linea.ida].reverse() };

    for (const [sentido, claves] of Object.entries(sentidos)) {
        const coordenadas = claves
            .map((k) => `${definicion.paradas[k].longitud},${definicion.paradas[k].latitud}`)
            .join(';');
        const url = `https://router.project-osrm.org/route/v1/driving/${coordenadas}?overview=full&geometries=geojson&steps=false`;

        const respuesta = await fetch(url, { headers: { 'User-Agent': AGENTE } });
        const datos = await respuesta.json();
        if (datos.code !== 'Ok') throw new Error(`Línea ${linea.numero} ${sentido}: ${datos.code} ${datos.message ?? ''}`);

        const ruta = datos.routes[0];

        claves.forEach((clave, i) => {
            const [lon, lat] = datos.waypoints[i].location.map((v) => +v.toFixed(6));
            const previa = salida.paradas[clave];
            if (previa && (previa.longitud !== lon || previa.latitud !== lat)) {
                console.warn(`Aviso: la parada ${clave} se ajustó distinto en otro recorrido (${previa.latitud},${previa.longitud} y ${lat},${lon})`);
            }
            salida.paradas[clave] ??= { latitud: lat, longitud: lon };
        });
        salida.ramales[`${linea.numero}-${sentido}`] = {
            distancia_osrm_m: Math.round(ruta.distance),
            puntos: ruta.geometry.coordinates.map(([lon, lat]) => [+lon.toFixed(6), +lat.toFixed(6)]),
        };
        console.log(`Línea ${linea.numero} ${sentido}: ${Math.round(ruta.distance)} m, ${ruta.geometry.coordinates.length} puntos`);
        await pausa(1200);
    }
}

writeFileSync('database/datos/recorridos.json', JSON.stringify(salida));
console.log('Guardado database/datos/recorridos.json');
