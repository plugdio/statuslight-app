FROM nginx:1.17-alpine

ARG DOMAIN
ARG EMAIL
ARG ADMINUSER
ARG ADMINPASS

RUN apk update; \
    apk upgrade; \
    apk add --no-cache certbot openssl;

# Copy renew cron script
COPY renew /etc/periodic/daily/renew
RUN chmod +x /etc/periodic/daily/renew

RUN mkdir /var/www
RUN mkdir /var/www/certbot

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN printf "${ADMINUSER}:$(openssl passwd -5 ${ADMINPASS})\n" >> /etc/nginx/.htpasswd

# nginx config
COPY app.conf /etc/nginx/conf.d/app.conf

RUN sed -i "s/MYDOMAIN/${DOMAIN}/" /entrypoint.sh && \
	sed -i "s/MYEMAIL/${EMAIL}/" /entrypoint.sh && \
	sed -i "s/MYEMAIL/${EMAIL}/" /entrypoint.sh && \
	sed -i "s/MYDOMAIN/${DOMAIN}/" /etc/nginx/conf.d/app.conf

ENTRYPOINT [ "/entrypoint.sh" ]
