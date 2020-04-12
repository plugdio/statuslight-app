FROM nginx:1.17-alpine
RUN apk update; \
    apk upgrade; \
    apk add --no-cache certbot;

# Copy renew cron script
COPY renew /etc/periodic/daily/renew
RUN chmod +x /etc/periodic/daily/renew

RUN mkdir /var/www
RUN mkdir /var/www/certbot

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# nginx config
COPY app.conf /etc/nginx/conf.d/app.conf

ENTRYPOINT [ "/entrypoint.sh" ]
