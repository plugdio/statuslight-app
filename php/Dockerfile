FROM php:7.3-fpm-alpine3.10
#RUN apk update; \
#    apk upgrade;
RUN apk add composer;
RUN cd /var/www/html && composer install;
#RUN docker-php-ext-install mysqli
