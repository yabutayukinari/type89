#!/bin/sh
# web サービス (nginx + php-fpm) の起動。
# 設定・ルート・イベントをキャッシュしてから supervisor を起動する。
# 環境変数は ECS タスク定義（SSM Parameter Store 由来）で注入される前提。
set -e

cd /var/www/html

# 実行時に env が揃った状態でパッケージ探索とキャッシュを作る
php artisan package:discover --ansi || true
php artisan config:cache
php artisan route:cache
php artisan event:cache || true

# storage シンボリックリンク（存在しなければ）
php artisan storage:link || true

exec supervisord -c /etc/supervisor/conf.d/app.conf -n
