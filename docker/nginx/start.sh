#!/bin/sh
until nc -z -v -w30 webalert-db 3306
do
  echo "Waiting for database connection..."
  sleep 5
done

cd /var/www/html

composer install --no-dev

php bin/console doctrine:database:create --env=prod
php bin/console doctrine:schema:update --force
php bin/console app:create-system-user

composer dump-env prod

mkdir -p /var/www/html/var/cache/prod
chmod -R 777 /var/www/html/var/cache/prod

php bin/console lexik:jwt:generate-keypair

php-fpm -D
status=$?
if [ $status -ne 0 ]; then
  echo "Failed to start PHP-FPM: $status"
  exit $status
fi

nginx -g 'daemon off;'
status=$?
if [ $status -ne 0 ]; then
  echo "Failed to start Nginx: $status"
  exit $status
fi

while sleep 60; do
  ps aux | grep php-fpm | grep -q -v grep
  PROCESS_1_STATUS=$?
  ps aux | grep nginx | grep -q -v grep
  PROCESS_2_STATUS=$?

  if [ $PROCESS_1_STATUS -ne 0 -o $PROCESS_2_STATUS -ne 0 ]; then
    echo "One of the processes has already exited."
    exit 1
  fi
done

