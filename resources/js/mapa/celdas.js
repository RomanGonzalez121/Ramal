// Misma grilla que app/Simulacion/Celdas.php. El navegador la usa para suscribirse solo a los canales
// de las celdas que se ven. tests/js/celdas.test.mjs repite los casos de CeldasTest.php para que no se desalineen.

export class Celdas {
    /**
     * @param {{caja:number[], columnas:number, filas:number}} configuracion  caja = [oeste, sur, este, norte]
     */
    constructor({ caja, columnas, filas }) {
        this.caja = caja;
        this.columnas = columnas;
        this.filas = filas;
    }

    columna(longitud) {
        const t = (longitud - this.caja[0]) / (this.caja[2] - this.caja[0]);
        return Math.max(0, Math.min(this.columnas - 1, Math.floor(t * this.columnas)));
    }

    /** La fila 0 es la de más al norte. */
    fila(latitud) {
        const t = (this.caja[3] - latitud) / (this.caja[3] - this.caja[1]);
        return Math.max(0, Math.min(this.filas - 1, Math.floor(t * this.filas)));
    }

    de(longitud, latitud) {
        return `${this.columna(longitud)}.${this.fila(latitud)}`;
    }

    /** @param {number[]} vista [oeste, sur, este, norte] */
    queCruzan([oeste, sur, este, norte]) {
        const celdas = [];
        for (let c = this.columna(oeste); c <= this.columna(este); c++) {
            for (let f = this.fila(norte); f <= this.fila(sur); f++) celdas.push(`${c}.${f}`);
        }
        return celdas;
    }
}
