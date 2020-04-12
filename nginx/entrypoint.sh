#!/bin/sh

# Get certs
certbot certonly --standalone -d test.statuslight.online --email janos@plugdio.com -n --agree-tos --expand

# Kick off cron
/usr/sbin/crond -f -d 8 &

# Start nginx
/usr/sbin/nginx -g "daemon off;"
