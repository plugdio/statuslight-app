FROM php:7.3-fpm-alpine3.10
#RUN apk update; \
#    apk upgrade;
#RUN docker-php-ext-install mysqli
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