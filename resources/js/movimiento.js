// Curvas y duraciones del sitio. Las mismas viven como variables CSS en resources/css/app.css
// (--ease-out, --ease-in-out): si se cambia una, hay que cambiar la otra.
//
// Regla: lo que entra o sale usa ease-out (arranca rápido, se siente que responde);
// lo que se mueve por la pantalla usa ease-in-out; nunca ease-in. Las salidas son más cortas que las entradas.

export const EASE_OUT = 'cubic-bezier(0.23, 1, 0.32, 1)';
export const EASE_IN_OUT = 'cubic-bezier(0.77, 0, 0.175, 1)';

/** Milisegundos. */
export const DURACION = {
    pulsoParada: 450, // el aviso de que llegó un colectivo: corto, pasa muchas veces por minuto
    dibujarLinea: 450, // el recorrido se traza de punta a punta
    camara: 450, // el mapa se acerca a una parada
    zoom: 250,
    tema: 450, // la transición circular Día/Noche
};
