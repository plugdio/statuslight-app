FROM php:7.3-fpm-alpine3.10
#RUN apk update; \
#    apk upgrade;
#RUN apk add composer;
#RUN docker-php-ext-install mysqli
COPY crontab.txt /crontab.txt
RUN /usr/bin/crontab /crontab.txt

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT [ "/entrypoint.sh" ]
