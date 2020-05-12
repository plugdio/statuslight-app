#!/bin/sh

set -e

sed -i.bak "s/XXX/${STATUSLIGHT_ENV}/" /crontab.txt
/usr/bin/crontab /crontab.txt

# Kick off cron
/usr/sbin/crond -f -d 8 &

# Start MQTT connection
nohup php /var/www/html/index.php "/mqttconnect" &

# Start nginx
docker-php-entrypoint php-fpm

exec "$@"