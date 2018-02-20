FROM eccube/php7-ext-codeception

MAINTAINER Kentaro Ohkouchi <nanasess@fsm.ne.jp>

### see also https://hub.docker.com/r/codeception/codeception/~/dockerfile/

RUN { \
    echo "date.timezone = Asia/Tokyo"; \
    echo "opcache.enable_cli = 1"; \
    echo "apc.enable_cli = 1"; \
    echo "memory_limit = -1"; \
} >> ${PHP_INI_DIR}/php.ini

## Add mailcatcher-codeception-module
WORKDIR /repo
RUN composer require captbaritone/mailcatcher-codeception-module "1.*"

## Clone EC-CUBE3

ENV ECCUBE_PATH /var/www
ENV BASE_URL="http://eccube3/"
ENV DATABASE_URL=${DATABASE_URL}
ENV DATABASE_SERVER_VERSION=${DATABASE_SERVER_VERSION}

ARG ECCUBE_REPOS
ARG ECCUBE_BRANCH
ARG DBTYPE

RUN cd /var && \
    git clone --depth=50 -b ${ECCUBE_BRANCH} ${ECCUBE_REPOS} ${ECCUBE_PATH}

WORKDIR ${ECCUBE_PATH}
RUN { \
    echo "APP_ENV=test"; \
    echo "APP_DEBUG=0"; \
} > ${ECCUBE_PATH}/.env
RUN composer install --no-interaction -o --apcu-autoloader

## create config yaml files
WORKDIR /project

## create dummy directories
RUN mkdir /project/html
RUN mkdir /project/app

COPY codecept /repo/codecept
RUN chmod +x /repo/codecept
