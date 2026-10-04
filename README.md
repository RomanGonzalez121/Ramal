# Ramal

Centro de control de colectivos en vivo para Paraná. Las líneas, los colectivos, los choferes y los incidentes son simulados; el mapa, el tiempo real, la estimación de llegada, la API y el historial son reales.

Estado: **M0 (identidad y movimiento)** hecho. Ver `CLAUDE.md` para el plan completo y `docs/decisiones.md` para las decisiones técnicas.

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

El mapa usa un recorte de Paraná en `resources/mapa/parana.pmtiles` (se regenera con `scripts/descargar-mapa.sh`). Si el WebSocket no está disponible, el mapa pasa solo a consultar `/api/posiciones` cada 2 segundos; con `VITE_TIEMPO_REAL=sondeo` ni lo intenta.

Para recalcular los recorridos y los desvíos (una sola vez cada uno, usan el servidor público de OSRM): `node scripts/calcular-recorridos.mjs` y `node scripts/calcular-desvios.mjs`, y después `php artisan db:seed --class=DesviosSeeder`.

Para que el centro de control y el rebobinado tengan un día completo (incidentes e historial de posiciones): apagar el simulador y correr `php artisan ramal:rellenar-dia --forzar` (tarda unos 17 minutos). El historial se guarda 48 horas.

## Tests

```bash
php artisan test     # PHP: contraste, tokens y página de identidad
npm run test:js      # JavaScript: interpolador de posiciones
vendor/bin/pint      # estilo
```
