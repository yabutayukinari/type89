#!/bin/sh
# reverb サービス (WebSocket) の起動。
# ECS タスク定義の command でこのスクリプトを指定する。
set -e

cd /var/www/html

php artisan package:discover --ansi || true
php artisan config:cache

exec php artisan reverb:start --host=0.0.0.0 --port=8080
