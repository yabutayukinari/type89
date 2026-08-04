# syntax=docker/dockerfile:1
#
# 本番/ステージング用の Laravel イメージ。
# web サービス (nginx + php-fpm) と reverb サービス (WebSocket) の両方をこの1イメージで賄い、
# ECS タスク定義側の command / entrypoint で役割を切り替える。
# フロントエンド (Next.js) は含まない（Amplify で別デプロイ）。

# ---- vendor: Composer 依存を本番構成で解決 ----
# --no-scripts で package:discover を走らせない（env が無いビルド時にコケるため）。
# パッケージ探索は実行時 (entrypoint) に行う。
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist --no-interaction

# ---- runtime ----
FROM php:8.4-fpm-bookworm AS runtime

# システム依存と PHP 拡張
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        nginx supervisor unzip \
        libzip-dev libpng-dev libonig-dev libxml2-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath zip; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# アプリ本体（vendor 込み）
COPY --from=vendor /app /var/www/html

# 各種設定
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/prod/entrypoint-web.sh /usr/local/bin/entrypoint-web.sh
COPY docker/prod/entrypoint-reverb.sh /usr/local/bin/entrypoint-reverb.sh

RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint-web.sh /usr/local/bin/entrypoint-reverb.sh; \
    chown -R www-data:www-data storage bootstrap/cache

# 80: nginx(web) / 8080: reverb(WebSocket)
EXPOSE 80 8080

# 既定は web。reverb サービスは ECS 側で entrypoint-reverb.sh に上書きする。
CMD ["/usr/local/bin/entrypoint-web.sh"]
