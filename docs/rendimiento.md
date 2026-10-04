# Rendimiento medido

Mediciones hechas el 4 de octubre de 2026, en una máquina de 2 núcleos con Windows, con el **build de producción** (`npm run build`), sin limitar la CPU ni la red. Son números locales: sirven para comparar entre cambios, no como promesa para internet.

## Mapa público (`/`)

| Medida | Resultado | Cómo se midió |
|---|---|---|
| LCP | 1,47 s (espera del servidor 0,55 s, el resto es esperar el CSS y las tipografías) | Traza de rendimiento de Chrome, recarga completa |
| CLS | 0,00 | Misma traza |
| Cuadros por segundo con los 40 colectivos | 60 (95 % de los cuadros en 17 ms, el peor en 18 ms) | Tres muestras de 4 segundos de `requestAnimationFrame`, con el mapa ya cargado |
| Colectivos visibles | 40 en menos de 5 s | Ver M4 |
| Accesibilidad (Lighthouse) | 100 en `/como-funciona` y `/api` | Auditoría de Lighthouse |

El código del mapa (MapLibre) pesa 300 kB comprimidos y solo se descarga en las páginas que lo usan.

## API (`scripts/prueba-de-carga.mjs`)

20 visitantes pidiendo a la vez, 25 pedidos cada uno, con `php artisan serve` (atiende de a un pedido) y el simulador corriendo en la misma máquina:

| | Antes de guardar `/api/mapa` en caché | Después |
|---|---|---|
| Pedidos por segundo | 9,3 | 14,8 |
| Mediana | 1571 ms | 1329 ms |
| 95 % de las respuestas | 4901 ms | 1505 ms |
| Errores | 0 | 0 |

Un visitante en el plan B pide una vez cada 2 segundos, así que ese servidor mínimo atiende a unos 30 visitantes a la vez. Con un servidor de verdad (Apache o PHP-FPM, que es lo que arma el `Dockerfile`) debería rendir bastante más; no se midió.

## Lo que se corrigió midiendo

- **El build de producción tenía el mapa roto.** MapLibre cargaba su trabajador desde un archivo que importa otro archivo que Vite no copiaba; en desarrollo andaba y en producción daba "Worker failed to load". Ahora el trabajador se compila aparte, completo.
- **`/api/mapa` tardaba hasta 1,4 s** porque armaba el mismo JSON en cada pedido. Ahora se guarda 5 minutos.
