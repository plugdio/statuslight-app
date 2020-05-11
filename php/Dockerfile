FROM php:7.3-fpm-alpine3.10
#RUN apk update; \
#    apk upgrade;
#RUN apk add composer;
#RUN docker-php-ext-install mysqli
ARG STATUSLIGHT_ENV
ENV STATUSLIGHT_ENV=$STATUSLIGHT_ENV
COPY crontab.txt /crontab.txt
RUN /usr/bin/crontab /crontab.txt

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

#ENTRYPOINT [ "/entrypoint.sh" ]
#CMD [$STATUSLIGHT_ENV]
ENTRYPOINT [ "sh", "-c", "./entrypoint.sh" ]