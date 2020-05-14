FROM php:7.3-fpm-alpine3.10
RUN apk update && apk --no-cache add libxml2-dev && \
	docker-php-source extract && \
	docker-php-ext-install -j$(nproc) xmlrpc && \
	docker-php-source delete

#apk add libxml2-dev
#RUN docker-php-source extract
#RUN docker-php-ext-install -j$(nproc) xmlrpc

RUN apk add supervisor
COPY supervisord.conf /etc/supervisor/supervisord.conf
#COPY crontab.txt /crontab.txt
#RUN /usr/bin/crontab /crontab.txt

# Copy entrypoint
#COPY entrypoint.sh /entrypoint.sh
#RUN chmod +x /entrypoint.sh

COPY listener.php /listener.php

#ENTRYPOINT [ "sh", "-c", "/entrypoint.sh" ]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
