#!/bin/sh
# Prepara la aplicación y deja andando el simulador junto con el servidor web.
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

# La primera vez, carga las 5 líneas y la cuenta de operador. Después no vuelve a tocar nada.
if [ "$(php artisan tinker --execute='echo App\Models\Linea::count();' 2>/dev/null | tail -n 1)" = "0" ]; then
    php artisan db:seed --force --no-interaction
    php artisan db:seed --class=DesviosSeeder --force --no-interaction || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# El simulador y las tareas programadas corren de fondo; el servidor web queda en primer plano.
php artisan schedule:work > /proc/1/fd/1 2>&1 &

exec "$@"
