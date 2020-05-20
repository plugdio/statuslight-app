#https://hub.docker.com/r/jllopis/mosquitto/dockerfile
FROM alpine:3.10

ARG DOMAIN
#ENV DOMAIN ${DOMAIN}

RUN addgroup -S mosquitto && \
    adduser -S -H -h /var/empty -s /sbin/nologin -D -G mosquitto mosquitto

ENV PATH=/usr/local/bin:/usr/local/sbin:$PATH
ENV MOSQUITTO_VERSION=1.6.9

COPY run.sh /

RUN apk --no-cache add --virtual buildDeps git cmake build-base openssl-dev c-ares-dev util-linux-dev curl-dev; \
    chmod +x /run.sh && \
    mkdir -p /var/lib/mosquitto && \
    touch /var/lib/mosquitto/.keep && \
    mkdir -p /etc/mosquitto.d && \
    apk add libuuid c-ares openssl curl ca-certificates && \
    wget http://mosquitto.org/files/source/mosquitto-${MOSQUITTO_VERSION}.tar.gz && \
    tar xzfv mosquitto-${MOSQUITTO_VERSION}.tar.gz && \
    mv mosquitto-${MOSQUITTO_VERSION} mosquitto && \
    rm mosquitto-${MOSQUITTO_VERSION}.tar.gz && \
    cd mosquitto && \
    make -j "$(nproc)" \
      WITH_SRV=yes \
      WITH_STRIP=yes \
      WITH_ADNS=no \
      WITH_DOCS=no \
      WITH_MEMORY_TRACKING=no \
      WITH_TLS_PSK=no \
      WITH_WEBSOCKETS=no \
    binary && \
    install -s -m755 client/mosquitto_pub /usr/bin/mosquitto_pub && \
    install -s -m755 client/mosquitto_rr /usr/bin/mosquitto_rr && \
    install -s -m755 client/mosquitto_sub /usr/bin/mosquitto_sub && \
    install -s -m644 lib/libmosquitto.so.1 /usr/lib/libmosquitto.so.1 && \
    ln -sf /usr/lib/libmosquitto.so.1 /usr/lib/libmosquitto.so && \
    install -s -m755 src/mosquitto /usr/sbin/mosquitto && \
    install -s -m755 src/mosquitto_passwd /usr/bin/mosquitto_passwd && \
    git clone https://github.com/vankxr/mosquitto-auth-plug && \
    cd mosquitto-auth-plug && \
    cp config.mk.in config.mk && \
    sed -i "s/BACKEND_CDB ?= no/BACKEND_CDB ?= no/" config.mk && \
    sed -i "s/BACKEND_MYSQL ?= yes/BACKEND_MYSQL ?= no/" config.mk && \
    sed -i "s/BACKEND_SQLITE ?= no/BACKEND_SQLITE ?= no/" config.mk && \
    sed -i "s/BACKEND_POSTGRES ?= no/BACKEND_POSTGRES ?= no/" config.mk && \
    sed -i "s/BACKEND_LDAP ?= no/BACKEND_LDAP ?= no/" config.mk && \
    sed -i "s/BACKEND_HTTP ?= no/BACKEND_HTTP ?= yes/" config.mk && \
    sed -i "s/BACKEND_JWT ?= no/BACKEND_JWT ?= no/" config.mk && \
    sed -i "s/BACKEND_MONGO ?= no/BACKEND_MONGO ?= no/" config.mk && \
    sed -i "s/BACKEND_FILES ?= no/BACKEND_FILES ?= no/" config.mk && \
    sed -i "s/BACKEND_MEMCACHED ?= no/BACKEND_MEMCACHED ?= no/" config.mk && \
    sed -i "s/MOSQUITTO_SRC =/MOSQUITTO_SRC = ..\//" config.mk && \
    make -j "$(nproc)" && \
    install -s -m755 auth-plug.so /usr/lib/ && \
    install -s -m755 np /usr/bin/ && \
    cd / && rm -rf mosquitto && \
    apk del buildDeps && rm -rf /var/cache/apk/*

COPY conf/auth-plugin.conf /etc/mosquitto.d/auth-plugin.conf
RUN sed -i "s/MYDOMAIN/${DOMAIN}/" /etc/mosquitto.d/auth-plugin.conf

# MQTT default port and default port over TLS
EXPOSE 1883 8883
# MQTT over websocket default port and default port over TLS
#EXPOSE 9001 9002

#VOLUME ["/var/lib/mosquitto", "/etc/mosquitto", "/etc/mosquitto.d"]

ENTRYPOINT ["/run.sh"]
CMD ["mosquitto"]