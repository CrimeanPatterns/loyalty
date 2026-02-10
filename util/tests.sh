#!/bin/bash
set -exo pipefail

export COMPOSE_FILE=docker-compose.yml:docker-compose-tests.yml

cd src/AppBundle/Engine
git checkout master
git pull
cd /www/loyalty
rm -Rf var/*
rm -Rf tests/_output/*

docker-compose down
docker-compose pull
docker volume rm -f loyalty_mysql-data
HOME=/home/user docker-compose up -d
set +e
HOME=/home/user docker-compose exec -T php util/tests-container.sh
exitCode=$?

#docker-compose down -v

exit $exitCode