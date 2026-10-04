// Estilo del mapa de Ramal: teselas vectoriales de Protomaps (PMTiles propio) pintadas con la paleta.
//
// Dos estilos con sentido propio: Día (el plano en papel) y Noche (el mapa oscuro).
// El mapa va apagado a propósito: los únicos colores saturados son los de las líneas,
// así los colectivos se leen al instante (lo que se aprendió de Flightradar24, ver docs/referencias).

import { layers, namedFlavor } from '@protomaps/basemaps';

/** Colores de las cinco líneas. Son los mismos de resources/css/app.css (un test lo comprueba). */
export const LINEAS = {
    dia: ['#9E2A3C', '#2F4CB3', '#16764B', '#B94A0B', '#7A3FA0'],
    noche: ['#F0788C', '#8BA0FF', '#4CC38A', '#FF9A55', '#C08BE6'],
};

const COLORES = {
    dia: {
        background: '#E3E6DF',
        earth: '#E3E6DF',
        park_a: '#CBD6C6',
        park_b: '#CBD6C6',
        wood_a: '#C9D5C3',
        wood_b: '#C9D5C3',
        scrub_a: '#D3DCCD',
        scrub_b: '#D3DCCD',
        hospital: '#DEDCD6',
        industrial: '#DCDFD6',
        school: '#DEDFD6',
        pedestrian: '#EEEFE9',
        sand: '#E7E5DA',
        beach: '#E7E5DA',
        zoo: '#CBD6C6',
        military: '#DCDFD6',
        aerodrome: '#DCDFD6',
        runway: '#F7F7F4',
        water: '#CDD9DD',
        buildings: '#D3D8CF',
        tunnel_other_casing: '#D3D8CF',
        tunnel_minor_casing: '#D3D8CF',
        tunnel_link_casing: '#D3D8CF',
        tunnel_major_casing: '#D3D8CF',
        tunnel_highway_casing: '#D3D8CF',
        tunnel_other: '#EEEFE9',
        tunnel_minor: '#EEEFE9',
        tunnel_link: '#EEEFE9',
        tunnel_major: '#EEEFE9',
        tunnel_highway: '#EEEFE9',
        other: '#F7F7F4',
        minor_service: '#F7F7F4',
        minor_service_casing: '#D3D8CF',
        minor_a: '#F7F7F4',
        minor_b: '#F7F7F4',
        minor_casing: '#D3D8CF',
        link: '#FFFFFF',
        link_casing: '#C4CABF',
        major: '#FFFFFF',
        major_casing_early: '#C4CABF',
        major_casing_late: '#C4CABF',
        highway: '#FFFFFF',
        highway_casing_early: '#B3BAAE',
        highway_casing_late: '#B3BAAE',
        railway: '#B8BFB3',
        boundaries: '#B8BFB3',
        bridges_other: '#F7F7F4',
        bridges_minor: '#F7F7F4',
        bridges_link: '#FFFFFF',
        bridges_major: '#FFFFFF',
        bridges_highway: '#FFFFFF',
        bridges_other_casing: '#D3D8CF',
        bridges_minor_casing: '#D3D8CF',
        bridges_link_casing: '#C4CABF',
        bridges_major_casing: '#C4CABF',
        bridges_highway_casing: '#B3BAAE',
        roads_label_minor: '#5B6168',
        roads_label_minor_halo: '#F7F7F4',
        roads_label_major: '#3E454B',
        roads_label_major_halo: '#F7F7F4',
        ocean_label: '#6D8791',
        subplace_label: '#5B6168',
        subplace_label_halo: '#E3E6DF',
        city_label: '#1E2328',
        city_label_halo: '#E3E6DF',
        state_label: '#5B6168',
        state_label_halo: '#E3E6DF',
        country_label: '#5B6168',
        address_label: '#5B6168',
        address_label_halo: '#E3E6DF',
    },
    noche: {
        background: '#14181C',
        earth: '#14181C',
        park_a: '#19231F',
        park_b: '#19231F',
        wood_a: '#18211D',
        wood_b: '#18211D',
        scrub_a: '#171E1B',
        scrub_b: '#171E1B',
        hospital: '#1F2125',
        industrial: '#181C20',
        school: '#1B1F23',
        pedestrian: '#1D2429',
        sand: '#1B1D1C',
        beach: '#1B1D1C',
        zoo: '#19231F',
        military: '#181C20',
        aerodrome: '#181C20',
        runway: '#2A3138',
        water: '#162027',
        buildings: '#1A2025',
        tunnel_other_casing: '#14181C',
        tunnel_minor_casing: '#14181C',
        tunnel_link_casing: '#14181C',
        tunnel_major_casing: '#14181C',
        tunnel_highway_casing: '#14181C',
        tunnel_other: '#20262C',
        tunnel_minor: '#20262C',
        tunnel_link: '#20262C',
        tunnel_major: '#20262C',
        tunnel_highway: '#20262C',
        other: '#2A3138',
        minor_service: '#242B31',
        minor_service_casing: '#14181C',
        minor_a: '#2A3138',
        minor_b: '#2A3138',
        minor_casing: '#14181C',
        link: '#323A42',
        link_casing: '#14181C',
        major: '#323A42',
        major_casing_early: '#14181C',
        major_casing_late: '#14181C',
        highway: '#3A444D',
        highway_casing_early: '#14181C',
        highway_casing_late: '#14181C',
        railway: '#3A444D',
        boundaries: '#3A444D',
        bridges_other: '#2A3138',
        bridges_minor: '#2A3138',
        bridges_link: '#323A42',
        bridges_major: '#323A42',
        bridges_highway: '#3A444D',
        bridges_other_casing: '#14181C',
        bridges_minor_casing: '#14181C',
        bridges_link_casing: '#14181C',
        bridges_major_casing: '#14181C',
        bridges_highway_casing: '#14181C',
        roads_label_minor: '#9AA1A8',
        roads_label_minor_halo: '#14181C',
        roads_label_major: '#C2C7CC',
        roads_label_major_halo: '#14181C',
        ocean_label: '#5E7683',
        subplace_label: '#9AA1A8',
        subplace_label_halo: '#14181C',
        city_label: '#F7F7F4',
        city_label_halo: '#14181C',
        state_label: '#9AA1A8',
        state_label_halo: '#14181C',
        country_label: '#9AA1A8',
        address_label: '#9AA1A8',
        address_label_halo: '#14181C',
    },
};

/** Lleva la selección a una expresión de opacidad: con una línea elegida, las demás bajan de intensidad. */
function opacidadRecorrido(seleccion, normal, apagada) {
    return seleccion
        ? ['case', ['==', ['get', 'linea'], seleccion], 1, apagada]
        : normal;
}

/**
 * Arma el estilo completo del mapa.
 *
 * @param {'dia'|'noche'} tema
 * @param {object} opciones
 * @param {string} opciones.urlTeselas  Dirección absoluta del archivo PMTiles.
 * @param {object} opciones.recorridos  GeoJSON con un LineString por ramal ({linea, sentido}).
 * @param {string} opciones.glifos      Plantilla de las tipografías ({fontstack} y {range}).
 * @param {number|null} opciones.seleccion Número de la línea elegida, o null.
 */
export function crearEstilo(tema, { urlTeselas, recorridos, glifos, seleccion = null, desvios = { type: 'FeatureCollection', features: [] } }) {
    const flavor = { ...namedFlavor(tema === 'noche' ? 'dark' : 'light'), ...COLORES[tema] };

    // Sin comercios ni servicios: ensucian el mapa y necesitarían un sprite de íconos aparte.
    const base = layers('protomaps', flavor, { lang: 'es' }).filter(
        (capa) => capa['source-layer'] !== 'pois' && !capa.layout?.['icon-image'],
    );

    const color = ['match', ['get', 'linea'], ...LINEAS[tema].flatMap((c, i) => [i + 1, c]), '#888888'];
    const ancho = ['interpolate', ['linear'], ['zoom'], 11, 2, 14, 4, 16, 6, 18, 9];

    return {
        version: 8,
        glyphs: glifos,
        sources: {
            protomaps: {
                type: 'vector',
                url: `pmtiles://${urlTeselas}`,
                attribution: 'OpenStreetMap y Protomaps',
            },
            recorridos: { type: 'geojson', data: recorridos },
            // Los caminos alternativos que están haciendo los colectivos desviados, ahora mismo.
            desvios: { type: 'geojson', data: desvios },
        },
        layers: [
            ...base,
            {
                id: 'recorridos-vuelta',
                type: 'line',
                source: 'recorridos',
                filter: ['==', ['get', 'sentido'], 'vuelta'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': color,
                    'line-width': ancho,
                    'line-dasharray': [0.2, 2],
                    'line-opacity': opacidadRecorrido(seleccion, 0.5, 0.1),
                },
            },
            {
                id: 'recorridos-ida',
                type: 'line',
                source: 'recorridos',
                filter: ['==', ['get', 'sentido'], 'ida'],
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': color,
                    'line-width': ancho,
                    'line-opacity': opacidadRecorrido(seleccion, 0.55, 0.1),
                },
            },
            // Un desvío se marca como en la calle: una tira punteada amarilla, con un borde de tinta para que se lea sobre cualquier fondo.
            {
                id: 'desvios-borde',
                type: 'line',
                source: 'desvios',
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: { 'line-color': tema === 'noche' ? '#F7F7F4' : '#1E2328', 'line-width': ['interpolate', ['linear'], ['zoom'], 11, 4, 16, 9, 18, 13] },
            },
            {
                id: 'desvios',
                type: 'line',
                source: 'desvios',
                layout: { 'line-join': 'round' },
                paint: {
                    'line-color': '#F5C400',
                    'line-width': ['interpolate', ['linear'], ['zoom'], 11, 2, 16, 5, 18, 8],
                    'line-dasharray': [1.2, 1.4],
                },
            },
        ],
    };
}

/** Pasa los desvíos de /api/desvios a GeoJSON. */
export function desviosGeoJSON(desvios) {
    return {
        type: 'FeatureCollection',
        features: desvios.map((d) => ({
            type: 'Feature',
            properties: { linea: d.linea, interno: d.interno },
            geometry: { type: 'LineString', coordinates: d.puntos },
        })),
    };
}

/** Pasa las líneas de /api/mapa a GeoJSON, un LineString por ramal. */
export function recorridosGeoJSON(lineas) {
    return {
        type: 'FeatureCollection',
        features: lineas.flatMap((linea) =>
            linea.ramales.map((ramal) => ({
                type: 'Feature',
                properties: { linea: linea.numero, sentido: ramal.sentido },
                geometry: { type: 'LineString', coordinates: ramal.recorrido },
            })),
        ),
    };
}

export function cambiarSeleccion(mapa, seleccion) {
    const normal = { 'recorridos-ida': [0.55, 0.1], 'recorridos-vuelta': [0.5, 0.1] };

    for (const [id, [n, a]] of Object.entries(normal)) {
        if (mapa.getLayer(id)) mapa.setPaintProperty(id, 'line-opacity', opacidadRecorrido(seleccion, n, a));
    }
}
