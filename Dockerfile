# syntax=docker/dockerfile:1

# Phase 1: FrankenPHP classic mode (no Octane yet)

# --- Stage 1: builder (PHP + Node, since wayfinder:generate needs `php artisan` during `npm run build`) ---
FROM dunglas/frankenphp:1-php8.5-bookworm AS builder

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    pcntl

# Node.jsバイナリのみを公式イメージから持ち込み、apt経由の古いバージョンを避ける
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY --from=composer:lts /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev

COPY package.json package-lock.json ./
RUN npm ci && npm run build

# --- Stage 2: runtime image (FrankenPHP worker mode) ---
FROM dunglas/frankenphp:1-php8.5-bookworm

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    pcntl

WORKDIR /app

COPY --from=builder /app ./

RUN php artisan storage:link

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Caddyfile(FrankenPHP同梱)はpublic/をdocument rootとして待ち受けるデフォルト設定をそのまま利用
ENV SERVER_NAME=":8000"
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
