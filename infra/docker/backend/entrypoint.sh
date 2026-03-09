#!/bin/sh
set -e

cd /var/www/backend

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "Running production migrations..."
  attempt=1
  until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 20 ]; then
      echo "Migration failed after ${attempt} attempts."
      exit 1
    fi

    echo "Migration attempt ${attempt} failed. Retrying in 3s..."
    attempt=$((attempt + 1))
    sleep 3
  done
fi

if [ "${RUN_SEEDERS:-0}" = "1" ]; then
  echo "Running production seeder..."
  php artisan db:seed --force --no-interaction
fi

exec "$@"
