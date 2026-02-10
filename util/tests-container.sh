#!/usr/bin/env bash
set -exo pipefail

echo $USER
echo $HOME

docker/wait-for-it.sh mail:25 -t 60
docker/wait-for-it.sh rabbitmq:5672 -t 120
docker/wait-for-it.sh mysql:3306 -t 60
docker/wait-for-it.sh memcached:11211 -t 60
docker/wait-for-it.sh mongo:27017 -t 60

rm -Rf app/config/parameters.yml
composer config --no-plugins allow-plugins.ocramius/package-versions true
composer install -n
SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/codecept --no-interaction build
SYMFONY_DEPRECATIONS_HELPER=disabled vendor/bin/codecept --no-interaction run --no-ansi --no-interaction --no-colors