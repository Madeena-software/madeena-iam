#!/bin/sh
set -e

cd /var/www/html

if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    _DB_WAIT=0
    until php -r "\$h=getenv('DB_HOST');\$p=getenv('DB_PORT')?:'3306';\$d=getenv('DB_DATABASE');\$u=getenv('DB_USERNAME');\$pw=getenv('DB_PASSWORD');new PDO(\"mysql:host=\$h;port=\$p;dbname=\$d\",\$u,\$pw);" 2>/dev/null; do
        _DB_WAIT=$((_DB_WAIT + 3))
        if [ "$_DB_WAIT" -ge 120 ]; then
            echo "[entrypoint] Database not ready after 120s; aborting."
            echo "[entrypoint] DB_HOST=$DB_HOST DB_PORT=${DB_PORT:-3306} DB_DATABASE=$DB_DATABASE DB_USERNAME=$DB_USERNAME"
            exit 1
        fi
        echo "[entrypoint] waiting (${_DB_WAIT}s elapsed)"
        sleep 3
    done
    echo "[entrypoint] Database is ready."
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache
rm -rf storage/framework/views/*.php storage/framework/cache/data/* bootstrap/cache/*.php
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
echo "[entrypoint] Storage directories scaffolded and cache cleared."

if ! php -r '$paths=["storage/app","storage/framework/cache/data","storage/framework/sessions","storage/framework/views","storage/logs","bootstrap/cache"];foreach($paths as $p){if(!is_dir($p)||!is_writable($p)){fwrite(STDERR,"[entrypoint] Invalid or non-writable path: $p\n");exit(1);}}'; then
    echo "[entrypoint] Laravel storage/cache paths are not ready."
    exit 1
fi

if [ $# -gt 0 ]; then
    exec "$@"
fi

echo "[entrypoint] Running Laravel bootstrap..."

if [ "${FILESYSTEM_DISK:-local}" = "local" ]; then
    php artisan storage:link --force 2>/dev/null || true
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache

if ! php artisan view:cache; then
    echo "[entrypoint] view:cache failed; continuing without cached views."
fi

echo "[entrypoint] Bootstrap complete. Starting services..."

if [ "${PHP_FPM_ONLY:-0}" = "1" ]; then
    echo "[entrypoint] PHP_FPM_ONLY=1; syncing public assets to shared volume..."
    cp -rT /var/www/html/public/. /var/www/public-files/
    echo "[entrypoint] Starting PHP-FPM..."
    exec /usr/local/sbin/php-fpm --nodaemonize
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
