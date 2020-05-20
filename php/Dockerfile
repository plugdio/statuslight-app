FROM php:7.3-fpm-alpine3.10

ARG DOMAIN
ARG STATICURL
ARG MQTTADMINUSER
ARG MQTTADMINPASS
ARG TEAMSCLIENTID
ARG TEAMSCLIENTSECRET
ARG GCALCLIENTID
ARG GCALCLIENTSECRET
ARG SLACKCLIENTID
ARG SLACKCLIENTSECRET

ENV DOMAIN ${DOMAIN}
ENV STATICURL: ${STATICURL}
ENV MQTTADMINUSER: ${MQTTADMINUSER}
ENV MQTTADMINPASS: ${MQTTADMINPASS}
ENV TEAMSCLIENTID: ${TEAMSCLIENTID}
ENV TEAMSCLIENTSECRET: ${TEAMSCLIENTSECRET}
ENV GCALCLIENTID: ${GCALCLIENTID}
ENV GCALCLIENTSECRET: ${GCALCLIENTSECRET}
ENV SLACKCLIENTID: ${SLACKCLIENTID}
ENV SLACKCLIENTSECRET: ${SLACKCLIENTSECRET}


RUN apk update && apk --no-cache add libxml2-dev && \
	docker-php-source extract && \
	docker-php-ext-install -j$(nproc) xmlrpc && \
	docker-php-source delete

RUN apk add supervisor
COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY listener.php /listener.php

COPY config_tpl.ini /var/www/html/config.ini

RUN sed -i "s/MYDOMAIN/$DOMAIN/" /var/www/html/config.ini && \
	sed -i "s/STATICURL/$STATICURL/" /var/www/html/config.ini && \
	sed -i "s/TEAMSCLIENTID/$TEAMSCLIENTID/" /var/www/html/config.ini && \
	sed -i "s/TEAMSCLIENTSECRET/$TEAMSCLIENTSECRET/" /var/www/html/config.ini && \
	sed -i "s/GCALCLIENTID/$GCALCLIENTID/" /var/www/html/config.ini && \
	sed -i "s/GCALCLIENTSECRET/$GCALCLIENTSECRET/" /var/www/html/config.ini && \
	sed -i "s/SLACKCLIENTID/$SLACKCLIENTID/" /var/www/html/config.ini && \
	sed -i "s/SLACKCLIENTSECRET/$SLACKCLIENTSECRET/" /var/www/html/config.ini && \
	sed -i "s/MQTTADMINUSER/$MQTTADMINUSER/" /var/www/html/config.ini && \
	sed -i "s/MYDOMAIN/$DOMAIN/" /var/www/html/config.ini && \
	sed -i "s/MQTTADMINPASS/$MQTTADMINPASS/" /var/www/html/config.ini

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
