# Stage 1: Build
FROM composer:2.8.2 AS builder
WORKDIR /var/www/html
COPY ./bin /var/www/html/bin
COPY ./config /var/www/html/config
COPY ./docker /var/www/html/docker
COPY ./migrations /var/www/html/migrations
COPY ./snapshots /var/www/html/snapshots
COPY ./public /var/www/html/public
COPY ./src /var/www/html/src
COPY ./templates /var/www/html/templates
COPY ./composer.json /var/www/html/composer.json

RUN rm -rf vendor
RUN apk add envsubst dos2unix
RUN export APP_SECRET=$(php -r 'echo bin2hex(random_bytes(16));') && \
    export JWT_PASSPHRASE=$(openssl rand -hex 32) && \
    envsubst < ./docker/nginx/env > .env
ARG ACCESS_CONTROL_ALLOW_ORIGIN
ENV ACCESS_CONTROL_ALLOW_ORIGIN=$ACCESS_CONTROL_ALLOW_ORIGIN
RUN envsubst '${ACCESS_CONTROL_ALLOW_ORIGIN}' < ./docker/nginx/default.conf.template > ./docker/nginx/default.conf
RUN dos2unix ./docker/nginx/default.conf
RUN dos2unix ./docker/nginx/start.sh
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: Production
FROM php:8.2.11-fpm-alpine3.18

COPY --from=builder /var/www/html/docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY ./docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY ./docker/nginx/start.sh /tmp/

RUN apk add --no-cache nginx && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable pdo_mysql && \
    chmod +x /tmp/start.sh

WORKDIR /var/www/html

COPY --from=builder /var/www/html /var/www/html
COPY --from=builder /var/www/html/.env /var/www/html/.env

RUN mkdir -p /run/nginx /var/lib/nginx/tmp/client_body /var/log/nginx && \
    chown -R www-data:www-data /run/nginx /var/www/html /var/lib/nginx /var/log/nginx

USER www-data

CMD ["/tmp/start.sh"]
