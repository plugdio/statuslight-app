#!/bin/sh

# Kick off cron
/usr/sbin/crond -f -d 8 &

set

# Start MQTT connection
nohup php /var/www/html/index.php "/mqttconnect" &

# Start nginx
docker-php-entrypoint php-fpm
