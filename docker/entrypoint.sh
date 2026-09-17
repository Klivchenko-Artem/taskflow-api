#!/bin/sh
set -e

cd /var/www

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

echo "Ждём PostgreSQL..."
until php -r "new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done
echo "База готова."

php artisan migrate --force
php artisan l5-swagger:generate
# Кеш конфигурации перекрывает переменные окружения целиком, включая настройки
# тестов, поэтому на стенде его не строим
if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "testing" ]; then
    php artisan config:clear
else
    php artisan config:cache
fi
php artisan route:cache

exec "$@"
