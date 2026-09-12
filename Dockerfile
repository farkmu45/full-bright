# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# build: PHP + Node together, because the Vite build runs
# `php artisan wayfinder:generate` through @laravel/vite-plugin-wayfinder.
# ---------------------------------------------------------------------------
FROM php:8.4-cli-alpine AS build

COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apk add --no-cache libstdc++ git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize \
    && npm run build:ssr

# runtime dependencies only; the SSR server keeps a pruned node_modules
RUN npm prune --omit=dev \
    && mv node_modules /node_modules \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ---------------------------------------------------------------------------
# app: php-fpm
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS app

RUN docker-php-ext-install pdo_mysql opcache pcntl

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# web: nginx serving public/ and proxying PHP to the app container
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/html/public

# ---------------------------------------------------------------------------
# ssr: Inertia server-side rendering (bootstrap/ssr/ssr.mjs on port 13714)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS ssr

WORKDIR /app
COPY --from=build /node_modules /app/node_modules
COPY --from=build /app/package.json /app/package.json
COPY --from=build /app/bootstrap/ssr /app/bootstrap/ssr

USER node
EXPOSE 13714
CMD ["node", "bootstrap/ssr/ssr.js"]
