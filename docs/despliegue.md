# Publicar Ramal

Ramal se publica como **un solo contenedor** (`Dockerfile`): el servidor web, el simulador y las tareas programadas juntos. La base de datos puede ser SQLite adentro del contenedor (plan gratuito) o una MySQL externa (si los datos tienen que durar).

> **Estado:** el `Dockerfile` y el `entrypoint` están escritos, pero **no se pudieron probar en esta máquina** (no hay un motor de Docker andando). Lo que sí se probó acá, sin Docker, es la secuencia del arranque con SQLite (migrar, cargar, rehacer 30 minutos y servir) y que los 254 tests pasan con las dos bases. El primer lugar donde se construye la imagen de verdad es el trabajo "Imagen de Docker" de la integración continua. Hasta que ese trabajo pase en verde y se haga el primer despliegue, hay que tomarlo como sin verificar.

## Elegido: Render (gratis), con SQLite que se rehace al despertar

Datos verificados el 4 de octubre de 2026 en la documentación de Render:

- **Plan gratuito:** el servicio web se duerme tras 15 minutos sin tráfico y tarda cerca de un minuto en despertar; tiene 750 horas por mes. **No ofrece MySQL gratis**: su base gratuita es PostgreSQL y se borra a los 30 días. La documentación consultada no aclara si el plan gratuito acepta imágenes de Docker (que es lo que usa el `Dockerfile`): hay que confirmarlo al crear el servicio. Si no las aceptara, habría que pasar al plan más barato.
- **La salida:** no usar una base aparte. Con `DB_CONNECTION=sqlite` la base es un archivo adentro del contenedor, y como el disco del plan gratuito se borra cada vez que el servicio se duerme o se vuelve a publicar, **al despertar todo se rehace solo**.

### Qué pasa en cada despertar

1. Se crea una base vacía, se corren las migraciones y se cargan las 5 líneas, los desvíos y la cuenta de operador (unos segundos).
2. Se rehacen los últimos 30 minutos (`RAMAL_RELLENO_MINUTOS`): historial para rebobinar, incidentes y posiciones. En esta máquina toda la secuencia tarda 25 segundos; en el plan gratuito, con mucha menos CPU, puede llevar unos minutos. Mientras tanto la web ya responde.
3. Arranca el simulador en vivo, que sigue desde donde terminó el relleno.

### Lo que se pierde al dormirse (y es parte del trato)

- **Los tokens de la API y las sesiones:** hay que volver a ingresar y crear un token. Es el costo de no tener una base que dure.
- **El historial anterior a los últimos 30 minutos** y los incidentes de antes.
- Los colectivos vuelven a la posición que les toca según la simulación rehecha, no donde estaban antes de dormirse.

### Pasos

1. En Render: *New, Blueprint*, elegir este repositorio. Lee `render.yaml`.
2. Completar las variables que quedan en blanco: `APP_KEY` (con `php artisan key:generate --show`), `APP_URL` (la dirección que dé Render) y `RAMAL_OPERADOR_PASSWORD`.
3. Mirar los registros de Render durante el primer arranque.

### Si algún día se necesita que los datos duren: MySQL externo

Cambiar `DB_CONNECTION` a `mysql` y cargar `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` y, si la base exige SSL, `DB_CA_PEM` con el certificado. Una opción gratuita sin vencimiento es el MySQL de Aiven (1 CPU, 1 GB de memoria y 1 GB de almacenamiento, sin tarjeta); otra es TiDB Cloud, compatible con MySQL. Ramal usa unos 10 MB para 48 horas de historial.

Para el WebSocket completo no alcanza el plan gratuito (hace falta un proceso siempre prendido); la demo funciona con la consulta cada 2 segundos.

## Variables de entorno del contenedor

| Variable | Valor |
|---|---|
| `APP_KEY` | Obligatoria. Se genera con `php artisan key:generate --show` |
| `APP_URL` | La dirección pública, con `https://` |
| `DB_CONNECTION` | `sqlite` (se rehace al despertar) o `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CA_PEM` | Solo con `mysql` |
| `RAMAL_RELLENO_MINUTOS` | Cuántos minutos de historial se rehacen al despertar con SQLite (30) |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database` |
| `BROADCAST_CONNECTION` | `log` si no hay Reverb |
| `RAMAL_SIEMPRE_EN_SERVICIO` | `true` para que haya colectivos a cualquier hora |
| `RAMAL_MOSTRAR_CUENTA_DEMO` | `true` solo si querés que se vea la cuenta de operador de demostración |
| `RAMAL_OPERADOR_PASSWORD` | Contraseña de la cuenta de operador (si no se pone, usa la de demostración, que es pública) |

No se suben claves al repositorio: todo esto va en el panel del proveedor.

## Cómo arranca el contenedor

1. Con SQLite, crea un archivo de base vacío. Espera a la base y corre las migraciones.
2. Si no hay líneas cargadas, carga las 5 líneas, los desvíos y la cuenta de operador.
3. Guarda en caché la configuración, las rutas y las vistas.
4. De fondo: con SQLite rehace los últimos minutos y después deja `php artisan schedule:work` (corre el simulador cada minuto y limpia el historial viejo). El servidor web queda en primer plano.

## Arranque en frío

En el plan gratuito, el primer ingreso después de un rato sin visitas tarda en responder porque el servicio se despierta (cerca de un minuto), y después el relleno de los últimos minutos termina de armarse de fondo.

## Si se pasa a un servidor propio

Con un VPS se pueden dejar prendidos Reverb, la cola y el simulador, y usar MySQL. Para eso se compila con `--build-arg VITE_TIEMPO_REAL=websocket` y se configuran las variables `REVERB_*` y `VITE_REVERB_*`.
