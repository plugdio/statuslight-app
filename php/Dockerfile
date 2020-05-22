FROM php:7.3-fpm-alpine3.10

RUN apk update && apk --no-cache add libxml2-dev && \
	docker-php-source extract && \
	docker-php-ext-install -j$(nproc) xmlrpc mysqli pdo pdo_mysql && \
	docker-php-source delete

#RUN docker-php-ext-install mysqli pdo pdo_mysql
#docker-php-ext-enable pdo_mysql

RUN apk add supervisor
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY listener.php /listener.php

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
