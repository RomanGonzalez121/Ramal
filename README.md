# Ramal

Centro de control de colectivos en vivo para Paraná. Las líneas, los colectivos, los choferes y los incidentes son simulados; el mapa, el tiempo real, la estimación de llegada, la API y el historial son reales.

Estado: módulos M0 a M10 hechos; M11 (calidad y publicación) en curso. Ver `CLAUDE.md` para el plan completo y `docs/decisiones.md` para las decisiones técnicas.

## Probarlo

Requiere PHP 8.3 o más, Composer, Node y MySQL 8.4 con las bases `ramal` y `ramal_test`.

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # carga las 5 líneas de Paraná
composer dev                 # servidor, Reverb, cola, simulador y Vite, todo junto
```

- `http://127.0.0.1:8000/` mapa público con los 40 colectivos en vivo.
- `/lineas` líneas y recorridos desde la base de datos.
- `/identidad` identidad visual y demostración del movimiento.
- `/operador` centro de control (ingreso con la cuenta de operador de demostración; ver `database/seeders/OperadorSeeder.php`).
- `/como-funciona` qué es simulado y qué es real, con una calculadora de llegada y el viaje de una posición hasta el mapa.
- `/api` documentación de la API pública, con botones para probar cada consulta. El documento OpenAPI está en `/api/openapi.json` y `/api/openapi.yaml`.

El mapa usa un recorte de Paraná en `resources/mapa/parana.pmtiles` (se regenera con `scripts/descargar-mapa.sh`). Si el WebSocket no está disponible, el mapa pasa solo a consultar `/api/posiciones` cada 2 segundos; con `VITE_TIEMPO_REAL=sondeo` ni lo intenta.

Para recalcular los recorridos y los desvíos (una sola vez cada uno, usan el servidor público de OSRM): `node scripts/calcular-recorridos.mjs` y `node scripts/calcular-desvios.mjs`, y después `php artisan db:seed --class=DesviosSeeder`.

Para que el centro de control y el rebobinado tengan un día completo (incidentes e historial de posiciones): apagar el simulador y correr `php artisan ramal:rellenar-dia --forzar` (tarda unos 17 minutos). El historial se guarda 48 horas.

## API pública

`/api/v1` devuelve líneas, paradas, posiciones en vivo y llegadas, y un feed de posiciones en GTFS Realtime (`/api/v1/gtfs-rt/posiciones`). Usa tokens de Sanctum con permiso de solo lectura:

1. Ingresá al panel de operador y abrí **Acceso a la API** (`/operador/api`). Creá un token: se muestra una sola vez.
2. Mandalo en la cabecera: `curl -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/v1/colectivos`.
3. Cada token tiene 60 pedidos por minuto (`RAMAL_API_LIMITE`). Los errores vienen siempre como `{ "mensaje": "..." }`.

El documento `resources/api/openapi.yaml` es la fuente de la página `/api`; un test comprueba que cada ruta de `/api/v1` esté documentada. `/api/v1/estado` no pide token. Las rutas `/api/mapa`, `/api/posiciones` y las demás sin `v1` son internas del sitio y pueden cambiar.

## Publicar

El `Dockerfile` arma un contenedor con la web, el simulador y las tareas programadas; la base MySQL va aparte. Detalles, variables de entorno y el aviso del arranque en frío en `docs/despliegue.md`. **El `Dockerfile` todavía no se probó:** lo construye por primera vez la integración continua.

La demo gratuita se duerme tras unos minutos sin visitas, así que el primer ingreso puede tardar. Mediciones de rendimiento (LCP, cuadros por segundo, carga de la API) en `docs/rendimiento.md`; la prueba de carga es `node scripts/prueba-de-carga.mjs`.

## Tests

```bash
php artisan test     # PHP: contraste, tokens y página de identidad
npm run test:js      # JavaScript: interpolador de posiciones
vendor/bin/pint      # estilo
```
