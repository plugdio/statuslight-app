#!/bin/sh

# Kick off cron
/usr/sbin/crond -f -d 8 &

# Start nginx
docker-php-entrypoint php-fpm
