##
## Stage 1: Download Layui assets (offline-local copy)
##
FROM node:20-alpine AS layui
WORKDIR /build
RUN npm init -y > /dev/null 2>&1 && npm install layui@2.9.8 --save --silent
RUN mkdir -p /layui-dist && \
    if [ -d node_modules/layui/dist ]; then \
        cp -r node_modules/layui/dist/* /layui-dist/; \
    elif [ -f node_modules/layui/dist/layui.js ]; then \
        cp -r node_modules/layui/dist/* /layui-dist/; \
    else \
        cp -r node_modules/layui/* /layui-dist/; \
    fi

##
## Stage 2: Composer install
##
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json /app/
RUN composer install --optimize-autoloader --no-scripts --ignore-platform-reqs 2>/dev/null || \
    composer install --no-scripts --ignore-platform-reqs

##
## Stage 3: Runtime (PHP-FPM + Nginx)
##
FROM php:8.1-fpm-alpine

RUN apk add --no-cache nginx curl bash openssl curl-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath opcache curl mbstring \
    && apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && mkdir -p /run/nginx /app/runtime/bootstrap /app/storage/uploads /app/storage/reports /app/storage/logs

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app

# Copy Layui assets
COPY --from=layui /layui-dist /app/public/static/layui/

# Copy Composer vendor
COPY --from=composer /app/vendor /app/vendor

# Copy application source
COPY . /app

# Ensure runtime dirs exist and register services
RUN mkdir -p /app/runtime/cache /app/runtime/log /app/runtime/session /app/runtime/temp \
    && php /app/think service:discover 2>/dev/null || true \
    && chown -R www-data:www-data /app/runtime /app/storage \
    && chmod +x /app/docker/entrypoint.sh /app/docker/init-db-internal.sh

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
