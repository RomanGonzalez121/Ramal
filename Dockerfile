# Ramal en un contenedor: un solo servicio con la web, el simulador y las tareas programadas.
#
#   docker build -t ramal .
#   docker run -p 8080:80 --env-file mi.env ramal
#
# En el plan gratuito de hosting no hay un servidor de WebSockets siempre prendido, así que por defecto
# el navegador usa el plan B de consulta cada 2 segundos. Con `--build-arg VITE_TIEMPO_REAL=websocket`
# (y un Reverb publicado aparte) se usa el WebSocket.

# ---------- 1. Los assets: Vite compila CSS y JavaScript y descarga las tipografías ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
ARG VITE_TIEMPO_REAL=sondeo
ENV VITE_TIEMPO_REAL=$VITE_TIEMPO_REAL
RUN npm run build

# ---------- 2. Las dependencias de PHP, sin las de desarrollo ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------- 3. La aplicación ----------
FROM php:8.3-apache AS app

RUN docker-php-ext-install pdo_mysql pcntl \
    && a2enmod rewrite headers expires deflate \
    && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build
COPY docker/apache-cache.conf /etc/apache2/conf-enabled/ramal-cache.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost/up || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
