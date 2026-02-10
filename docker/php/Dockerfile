ARG PHP_VERSION=7.4
ARG NODE_VERSION=16.17.0
ARG ARCH_GITHUB=arm64

FROM php:${PHP_VERSION}-fpm AS phpspy
RUN set -eux; \
    apt-get update; \
    apt-get install -y build-essential git python
RUN set -eux; \
    cd /tmp; \
    git clone https://github.com/adsr/phpspy.git
RUN set -eux; \
    cd /tmp/phpspy; \
    git checkout 47f0abb1a36216b71b524f9bc7858547c7ff3053; \
    make

FROM php:${PHP_VERSION}-fpm AS node
ARG ARCH_GITHUB
ARG NODE_VERSION
RUN \
    set -eux pipefail; \
    apt-get update && apt-get install -y --no-install-recommends wget; \
    mkdir /usr/local/lib/nodejs; \
    wget -q "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${ARCH_GITHUB}.tar.xz" -O node.tar.xz; \
    tar -xJvf node.tar.xz -C /usr/local/lib/nodejs

FROM php:${PHP_VERSION}-fpm AS base
ARG ARCH_GITHUB
ARG NODE_VERSION
RUN apt-get update && apt-get install -y unzip libzip-dev \
	&& pecl install zip \
	&& docker-php-ext-enable zip \
    && rm -r /tmp/* /var/cache/*
# pcntl for caching SSM parameters
RUN docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl \
    && docker-php-ext-enable pcntl
RUN apt-get update && apt-get install -y libmemcached-dev zlib1g-dev \
    && pecl install memcached-3.2.0 \
    && docker-php-ext-enable memcached \
    && rm -r /tmp/* /var/cache/*
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
RUN docker-php-ext-install sockets \
    && docker-php-ext-enable sockets
# apcu - for caching SSM parameter (ssm-env-var-processor)
RUN pecl install apcu \
    && docker-php-ext-enable apcu
COPY --from=phpspy /tmp/phpspy/phpspy /root/
# symfony config
ENV SYMFONY_ENV=prod
ENV SYMFONY_DEBUG=0
# ini settings
COPY aw-common.ini /usr/local/etc/php/conf.d/
# mysql
RUN docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd && docker-php-ext-install pdo_mysql
# smtp
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    msmtp-mta \
    ; \
    rm -rf /var/lib/apt/lists/*
COPY msmtprc /etc/
# admin tools
RUN apt-get update && apt-get install -y procps nano \
    && rm -r /tmp/* /var/cache/*
# production config
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY aw-production.ini /usr/local/etc/php/conf.d/
# opcache
RUN docker-php-ext-install opcache \
    && docker-php-ext-enable opcache
# shmop_open for caching SSM parameters
RUN docker-php-ext-configure shmop --enable-shmop \
    && docker-php-ext-install shmop \
    && docker-php-ext-enable shmop \
# gosu for entrypoint
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    gosu \
    ; \
    rm -rf /var/lib/apt/lists/*

FROM base AS worker
# ffmpeg for amazon gift parser, see GifFpsChanger class
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    msmtp-mta \
    ; \
    rm -rf /var/lib/apt/lists/*
# pdftohtml, ptftotext, see \PDF class
RUN apt-get update && apt-get install -y poppler-utils --no-install-recommends && rm -rf /var/lib/apt/lists/*
# gd with jpeg, png and webp for captcha
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp && docker-php-ext-install gd
# SOAP for American Airlines api client, see src/AppBundle/Engine/aa/wsdl/lmsv4/LoyaltyMemberSecurityService.php \
RUN apt-get update && apt-get install -y --no-install-recommends libxml2-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install soap
# Tidy for html filtering in HttpBrowser
RUN apt-get update && apt-get install -y --no-install-recommends libtidy-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install tidy
# for svg support. #21480
RUN apt-get update && apt-get install -y libmagickwand-dev --no-install-recommends && rm -rf /var/lib/apt/lists/* \
    && pecl install imagick \
    && docker-php-ext-enable imagick
# for running puppeteer. see getPuppeteerExecutor method. example src/AppBundle/Engine/testprovider/Chrome/Puppeteer.php
COPY --from=node /usr/local/lib/nodejs/ /usr/local/lib/nodejs/
RUN \
    set -eux pipefail; \
    ln -s /usr/local/lib/nodejs/node-v${NODE_VERSION}-linux-${ARCH_GITHUB}/bin/node /usr/bin/node
ENV PATH=/usr/local/lib/nodejs/node-v${NODE_VERSION}-linux-${ARCH_GITHUB}/bin:$PATH
COPY aw-cli.ini /usr/local/etc/php/conf.d/
# update timezone information for new timezones like 'America/Ciudad_Juarez'
RUN pecl install timezonedb && bash -c "echo extension=timezonedb.so >/usr/local/etc/php/conf.d/timezonedb.ini"

FROM worker AS build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git ssh gosu \
    && rm -r /var/cache/*
RUN chmod +s /usr/sbin/gosu
COPY entrypoint-dev-cli.sh /opt/
ENTRYPOINT ["/opt/entrypoint-dev-cli.sh"]
COPY sudo /usr/local/bin/

FROM build AS debug
RUN pecl install xdebug-3.1.5 \
	&& docker-php-ext-enable xdebug
ENV SYMFONY_ENV=dev
ENV SYMFONY_DEBUG=1
COPY aw-xdebug.ini /usr/local/etc/php/conf.d/
RUN sed -i s/www-data/user/g /usr/local/etc/php-fpm.d/www.conf
COPY xdebug /usr/local/bin/
#RUN mv /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /opt/
COPY console /usr/local/bin/
# dev config
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini && rm /usr/local/etc/php/conf.d/aw-production.ini
COPY aw-development.ini /usr/local/etc/php/conf.d/

#RUN ssh-keyscan github.com >> /opt/known_hosts

FROM base AS web
COPY aw-fpm.ini /usr/local/etc/php/conf.d/
COPY www.conf /usr/local/etc/php-fpm.d/www.conf
#COPY . /app/
#WORKDIR /app/
#RUN \
#  --mount=type=bind,from=composer:2,source=/usr/bin/composer,target=/usr/local/bin/composer \
#  composer install
