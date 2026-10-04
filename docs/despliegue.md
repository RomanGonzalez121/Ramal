# Publicar Ramal

Ramal se publica como **un solo contenedor** (`Dockerfile`): el servidor web, el simulador y las tareas programadas juntos, más una base MySQL aparte.

> **Estado:** el `Dockerfile` y el `entrypoint` están escritos, pero **no se pudieron probar en esta máquina** (no hay un motor de Docker andando). El primer lugar donde se construyen de verdad es el trabajo "Imagen de Docker" de la integración continua. Hasta que ese trabajo pase en verde y se haga el primer despliegue, hay que tomarlos como sin verificar.

## Qué hace falta

| Pieza | Dónde |
|---|---|
| Servicio web (el contenedor) | Cualquier proveedor que corra imágenes de Docker. En un plan gratuito se duerme tras unos minutos sin visitas |
| Base MySQL 8 | Aparte. Los planes gratuitos de web casi nunca incluyen MySQL: hay que usar una base externa (por ejemplo, un MySQL gratuito de otro proveedor) |
| Servidor de WebSockets (Reverb) | Opcional. Necesita un proceso siempre prendido, que el plan gratuito no da |

## Elegido: Render (web, gratis) más Aiven (MySQL, gratis)

Datos verificados el 4 de octubre de 2026 en la documentación de cada uno:

- **Render, plan gratuito:** el servicio web se duerme tras 15 minutos sin tráfico y tarda cerca de un minuto en despertar; tiene 750 horas por mes. **No ofrece MySQL gratis**: su base gratuita es PostgreSQL y se borra a los 30 días. Por eso la base va en otro lado. (La documentación consultada no aclara si el plan gratuito acepta imágenes de Docker; hay que confirmarlo al crear el servicio. Si no las aceptara, habría que pasar al plan más barato.)
- **Aiven, plan gratuito de MySQL:** 1 CPU, 1 GB de memoria y 1 GB de almacenamiento, sin vencimiento ni tarjeta. Ramal usa unos 10 MB para 48 horas de historial.

### Pasos

1. En Aiven, crear un servicio **MySQL** gratuito, en la región más cercana a la de Render. Copiar el host, el puerto, la base, el usuario, la contraseña y el contenido del certificado de la CA.
2. En Render: *New, Blueprint*, elegir este repositorio. Lee `render.yaml`.
3. Completar las variables que quedan en blanco: `APP_KEY` (con `php artisan key:generate --show`), `APP_URL` (la dirección que dé Render), los datos de la base, `DB_CA_PEM` (el certificado, pegado tal cual) y `RAMAL_OPERADOR_PASSWORD`.
4. La primera vez, el contenedor migra y carga las líneas solo. Mirar los registros de Render si algo falla.

Para el WebSocket completo en esta combinación no alcanza el plan gratuito (hace falta un proceso siempre prendido); la demo funciona con la consulta cada 2 segundos.

## Variables de entorno del contenedor

| Variable | Valor |
|---|---|
| `APP_KEY` | Obligatoria. Se genera con `php artisan key:generate --show` |
| `APP_URL` | La dirección pública, con `https://` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | La base MySQL |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` |
| `BROADCAST_CONNECTION` | `log` si no hay Reverb |
| `RAMAL_SIEMPRE_EN_SERVICIO` | `true` para que haya colectivos a cualquier hora |
| `RAMAL_MOSTRAR_CUENTA_DEMO` | `true` solo si querés que se vea la cuenta de operador de demostración |
| `RAMAL_OPERADOR_PASSWORD` | Contraseña de la cuenta de operador (si no se pone, usa la de demostración, que es pública) |

No se suben claves al repositorio: todo esto va en el panel del proveedor.

## Cómo arranca

1. Espera a la base y corre las migraciones.
2. Si no hay líneas cargadas, carga las 5 líneas, los desvíos y la cuenta de operador (una sola vez).
3. Guarda en caché la configuración, las rutas y las vistas.
4. Deja `php artisan schedule:work` de fondo (corre el simulador cada minuto y limpia el historial viejo) y el servidor web en primer plano.

## Arranque en frío

En el plan gratuito, el primer ingreso después de un rato sin visitas tarda en responder porque el servicio se despierta. El simulador también estuvo dormido: las posiciones retoman desde donde quedaron. Para llenar el historial de un día entero (el rebobinado) hay que correr `php artisan ramal:rellenar-dia --forzar` dentro del contenedor (tarda unos 17 minutos).

## Si se pasa a un servidor propio

Con un VPS se pueden dejar prendidos Reverb, la cola y el simulador. Para eso se compila con `--build-arg VITE_TIEMPO_REAL=websocket` y se configuran las variables `REVERB_*` y `VITE_REVERB_*`.
