#!/bin/sh
# Миграции + сидеры при старте контейнера (app и worker), затем основная команда.
set -e

cd /var/www

echo "==> Миграции БД"
php bin/console.php app:migrate

echo "==> Сидеры"
php bin/console.php db:seed || echo "!! Сидинг пропущен (уже выполнен?)"

exec "$@"
