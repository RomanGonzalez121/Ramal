#!/bin/sh
# Prepara la aplicación y deja andando el simulador junto con el servidor web.
#
# Dos modos de base de datos:
#  - MySQL externo (DB_CONNECTION=mysql): los datos duran entre reinicios.
#  - SQLite adentro del contenedor (DB_CONNECTION=sqlite, el modo del plan gratuito de Render): el disco se borra cada
#    vez que el servicio se duerme o se vuelve a publicar, así que al despertar todo se rehace solo: las migraciones,
#    las líneas, la cuenta de operador y los últimos minutos de historial.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "Falta APP_KEY. Generá una con: php artisan key:generate --show" >&2
    exit 1
fi

# Las bases gestionadas (Aiven, TiDB) exigen SSL con su propio certificado. Se pasa su contenido en DB_CA_PEM
# (así no hay un archivo con claves en el repositorio) y acá se guarda donde Laravel lo busca.
if [ -n "$DB_CA_PEM" ]; then
    echo "$DB_CA_PEM" > /etc/ssl/ramal-db-ca.pem
    export MYSQL_ATTR_SSL_CA=/etc/ssl/ramal-db-ca.pem
fi

if [ "$DB_CONNECTION" = "sqlite" ]; then
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/ramal.sqlite}"
    mkdir -p "$(dirname "$DB_DATABASE")"
    : > "$DB_DATABASE"   # base nueva y vacía en cada arranque
fi

# Espera a la base de datos (el plan gratuito a veces la despierta después de la web).
intentos=0
until php artisan migrate --force --no-interaction; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 20 ]; then
        echo "No se pudo conectar con la base de datos." >&2
        exit 1
    fi
    echo "Esperando la base de datos ($intentos)..."
    sleep 3
done

# La primera vez (o siempre, con SQLite), carga las 5 líneas y la cuenta de operador. Después no vuelve a tocar nada.
if [ "$(php artisan tinker --execute='echo App\Models\Linea::count();' 2>/dev/null | tail -n 1)" = "0" ]; then
    php artisan db:seed --force --no-interaction
    php artisan db:seed --class=DesviosSeeder --force --no-interaction || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# El servidor web (www-data) y el simulador tienen que poder escribir los mismos archivos.
chown -R www-data:www-data storage bootstrap/cache

# El simulador y las tareas programadas corren de fondo; el servidor web queda en primer plano.
# Con SQLite, antes de arrancar el simulador se rehacen los últimos minutos para que el rebobinado y el panel no estén vacíos.
(
    if [ "$DB_CONNECTION" = "sqlite" ]; then
        runuser -u www-data -- php artisan ramal:rellenar-dia --ultimos="${RAMAL_RELLENO_MINUTOS:-30}" --paso=10 --forzar --no-interaction
    fi
    exec runuser -u www-data -- php artisan schedule:work
) > /proc/1/fd/1 2>&1 &

exec "$@"
