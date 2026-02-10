ARG BASE_IMAGE
FROM ${BASE_IMAGE}

ARG symfony_env
ENV SYMFONY_ENV=$symfony_env
ENV SSM_WARMUP 1

COPY . /www/loyalty/current
WORKDIR /www/loyalty/current
RUN chown -R www-data:www-data /www/loyalty/current

RUN mkdir /usr/keys
RUN mv app/config/*.pem /usr/keys/

RUN set -eux; \
    DEBIAN_FRONTEND=noninteractive apt-get update ; \
	DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        supervisor ; \
	rm -rf /var/lib/apt/lists/*



