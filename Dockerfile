FROM dunglas/frankenphp:1-php8.4-alpine AS base
WORKDIR /app
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions curl mbstring opcache
COPY config/php-production.ini "$PHP_INI_DIR/conf.d/zz-swapfix.ini"

FROM base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache unzip
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY app ./app
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM base AS runtime
COPY --from=dependencies /app/vendor ./vendor
COPY Caddyfile /etc/caddy/Caddyfile
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY resources ./resources
COPY public ./public
ENV APP_ENV=production PORT=80 XDG_CONFIG_HOME=/tmp/caddy-config XDG_DATA_HOME=/tmp/caddy-data
EXPOSE 80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
