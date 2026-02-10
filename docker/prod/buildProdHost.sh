#!/bin/bash -x

set -euxo pipefail

export PYTHONUNBUFFERED=1

function clean_images(){
    IMAGE=$1
    docker rmi -f $(docker images | grep -F $REGISTRY/loyalty | grep $IMAGE | grep -F -v $TAG | tail -n +2 | awk '{ print $1":"$2; }' | sort | uniq) || true
}

if [[ "$pull" == "true" ]]; then
  git checkout $branch
  git reset --hard origin/$branch
  git submodule init
  git submodule foreach "git checkout master && git reset --hard origin/master && git pull"
fi

TAG=`git rev-parse HEAD`
REGISTRY=718278292471.dkr.ecr.us-east-1.amazonaws.com
CONFIG_FILE=parameters

if [[ "$config" == "prod-ecs" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers-4"
  ECS_WEB_SERVICE_ATTRIBUTE="web"
  WORKERS_SERVICE_NAME="workers-4"
  WEB_SERVICE_NAME="web"
  CLUSTER_NAME=loyalty
  PARAMETERS_FILE=parameters.yml
  DEPLOY_AWS_PROFILE=default
  APP_NAME=loyalty
  SYMFONY_ENV=prod_awardwallet
  NFS_OPTIONS=addr=engine-files.awardwallet.com,soft,ro,timeo=14,nfsvers=4
  BASE_IMAGE_VER=10
  TRUSTED_PROXIES=192.168.0.0/16
  CONFIG_FILE="config_${SYMFONY_ENV}"
fi

if [[ "$config" == "prod-ecs-2" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers-4"
  ECS_WEB_SERVICE_ATTRIBUTE="web-2"
  WORKERS_SERVICE_NAME="workers-4"
  WEB_SERVICE_NAME="web-2"
  CLUSTER_NAME=loyalty
  PARAMETERS_FILE=parameters.yml
  DEPLOY_AWS_PROFILE=default
  APP_NAME=loyalty
  SYMFONY_ENV=prod_awardwallet
  NFS_OPTIONS=addr=engine-files.awardwallet.com,soft,ro,timeo=14,nfsvers=4
  BASE_IMAGE_VER=10
  TRUSTED_PROXIES=192.168.0.0/16
  CONFIG_FILE="config_${SYMFONY_ENV}"
fi

if [[ "$config" == "beta" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers-beta"
  ECS_WEB_SERVICE_ATTRIBUTE="web-2"
  WORKERS_SERVICE_NAME="workers-beta"
  WEB_SERVICE_NAME="web-beta"
  CLUSTER_NAME=loyalty
  PARAMETERS_FILE=parameters-beta.yml
  DEPLOY_AWS_PROFILE=default
  APP_NAME=loyalty-beta
  SYMFONY_ENV=prod_awardwallet
  BASE_IMAGE_VER=10
  CONFIG_FILE="config_${SYMFONY_ENV}"
  SYMFONY_ENV=prod_awardwallet
  NFS_OPTIONS=addr=engine-files.awardwallet.com,soft,ro,timeo=14,nfsvers=4
  TRUSTED_PROXIES=192.168.0.0/16
fi

if [[ "$config" == "juicymiles" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers"
  ECS_WEB_SERVICE_ATTRIBUTE="web"
  WORKERS_SERVICE_NAME="workers"
  WEB_SERVICE_NAME="web"
  CLUSTER_NAME=main
  PARAMETERS_FILE=parameters-juicymiles.yml
  DEPLOY_AWS_PROFILE=juicymiles
  APP_NAME=juicymiles
  SYMFONY_ENV=prod
  NFS_OPTIONS=addr=engine-files.awardwallet.com,soft,ro,timeo=14
  BASE_IMAGE_VER=10
  TRUSTED_PROXIES=172.30.0.0/16
fi

if [[ "$config" == "juicymiles-beta" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers"
  ECS_WEB_SERVICE_ATTRIBUTE="web-beta"
  WORKERS_SERVICE_NAME="workers"
  WEB_SERVICE_NAME="web-beta"
  CLUSTER_NAME=beta
  PARAMETERS_FILE=parameters-juicymiles-beta.yml
  DEPLOY_AWS_PROFILE=juicymiles
  APP_NAME=juicymiles-beta
  SYMFONY_ENV=prod
  NFS_OPTIONS=addr=engine-files.awardwallet.com,soft,ro,timeo=14
  BASE_IMAGE_VER=10
  TRUSTED_PROXIES=172.30.0.0/16
fi

if [[ "$config" == "ra-awardwallet" ]]; then
  ECS_WORKER_SERVICE_ATTRIBUTE="workers"
  ECS_WEB_SERVICE_ATTRIBUTE="web"
  WORKERS_SERVICE_NAME="workers"
  WEB_SERVICE_NAME="web"
  CLUSTER_NAME=main
  PARAMETERS_FILE=parameters-ra-awardwallet.yml
  DEPLOY_AWS_PROFILE=ra-awardwallet
  APP_NAME=ra-awardwallet
  SYMFONY_ENV=prod_ra_awardwallet
  NFS_OPTIONS=addr=files.infra.awardwallet.com,soft,ro,timeo=14,nfsvers=4
  BASE_IMAGE_VER=10
  TRUSTED_PROXIES=172.35.0.0/16
fi

if [[ "$tests" == "true" ]]; then
  ssh -i  ~/.ssh/ansible2.pem  user@test-runner.infra.awardwallet.com "cd /www/loyalty && git fetch origin && git reset --hard origin/$branch && util/tests.sh $TAG"
fi

TAG="$TAG-$config"
PYTHONUNBUFFERED=1

rsync ~/vars/loyalty/$PARAMETERS_FILE app/config/parameters.yml
rsync ~/vars/aa/*.pem app/config/
rsync ~/vars/loyalty/*.pem app/config/
rsync ~/vars/awardwalletPremiumSslEv*.pem app/config/
rsync ~/vars/bankofamerica/bankofamerica-*.pem app/config/

if [[ "$build" == "true" ]]; then
  rm -Rf var/cache/*
  rm -Rf var/logs/*
  mkdir -p ~/.npm

  docker pull docker.awardwallet.com/php/loyalty-php7.4-build-multiarch-amd64-v$BASE_IMAGE_VER
  docker pull docker.awardwallet.com/php/loyalty-php7.4-worker-multiarch-amd64-v$BASE_IMAGE_VER
  docker pull docker.awardwallet.com/php/loyalty-php7.4-web-multiarch-amd64-v$BASE_IMAGE_VER

  docker run -e LOCAL_USER_ID=`id -u $USER` -u `id -u $USER` \
    -e http_proxy=$http_proxy --rm \
    -e SYMFONY_ENV=$SYMFONY_ENV \
    -e MYSQL_PASSWORD=xxx \
    -v `pwd`:/www/loyalty/current \
    -v ~/.composer:/home/user/.composer \
    -v ~/.gitconfig:/home/user/.gitconfig \
    -v ~/.ssh:/home/user/.ssh \
    -v ~/.npm:/home/user/.npm \
    -v ~/.npmrc:/home/user/.npmrc \
    docker.awardwallet.com/php/loyalty-php7.4-build-multiarch-amd64-v$BASE_IMAGE_VER \
    bash -c 'cd /www/loyalty/current && rm -Rf var/cache/* && composer config --no-plugins allow-plugins.ocramius/package-versions true && composer install --optimize-autoloader --no-dev --apcu-autoloader && bin/console cache:warmup'

if [[ "$SYMFONY_ENV" == "prod" ]]; then
  CONFIG_FILE=parameters.yml
else
  CONFIG_FILE="config_$SYMFONY_ENV.yml"
fi

  # get rabbit host
  RABBIT_HOST=`
  set -eu pipefail;
  echo "import yaml
with open('app/config/$CONFIG_FILE', 'r') as stream:
    config = yaml.safe_load(stream)
    print(config['parameters']['env(RABBITMQ_HOST)'])
" | python
`
echo "rabbit host: $RABBIT_HOST";

  docker build -t aw-loyalty-prod-worker --build-arg symfony_env=$SYMFONY_ENV --build-arg BASE_IMAGE=docker.awardwallet.com/php/loyalty-php7.4-worker-multiarch-amd64-v$BASE_IMAGE_VER --build-arg http_proxy=$http_proxy -f docker/prod/prod.Dockerfile .
  if [[ "$config" == "prod" ]]; then
    docker tag aw-loyalty-prod-worker aw-loyalty-builder
  fi
  docker build -t aw-loyalty-prod-web --build-arg symfony_env=$SYMFONY_ENV --build-arg BASE_IMAGE=docker.awardwallet.com/php/loyalty-php7.4-web-multiarch-amd64-v$BASE_IMAGE_VER --build-arg http_proxy=$http_proxy -f docker/prod/prod.Dockerfile .

  docker build -t $REGISTRY/loyalty:worker-$TAG --build-arg http_proxy=$http_proxy -f docker/prod/prod-worker.Dockerfile docker
  clean_images worker

  docker build -t $REGISTRY/loyalty:nginx-$TAG --build-arg http_proxy=$http_proxy -f docker/prod/prod-nginx.Dockerfile .
  clean_images nginx

  docker build -t $REGISTRY/loyalty:fpm-$TAG --build-arg http_proxy=$http_proxy --build-arg trusted_proxies=$TRUSTED_PROXIES -f docker/prod/prod-fpm.Dockerfile docker
  clean_images fpm

  docker build -t $REGISTRY/loyalty:fluentbit-$TAG --build-arg APP_NAME=$APP_NAME docker/prod/fluentbit
  clean_images fluentbit

  aws ecr get-login-password | docker login --username AWS --password-stdin 718278292471.dkr.ecr.us-east-1.amazonaws.com
  docker push $REGISTRY/loyalty:worker-$TAG
  docker push $REGISTRY/loyalty:nginx-$TAG
  docker push $REGISTRY/loyalty:fpm-$TAG
  docker push $REGISTRY/loyalty:fluentbit-$TAG

  if [[ "$config" == "prod" ]]; then
    docker-compose up -d fluent mail
  fi
fi

export AWS_PROFILE=$DEPLOY_AWS_PROFILE

if [[ "$deploy_workers" == "true" ]]; then
  # before migrations, we will use worker task def to run migrations
  cat docker/prod/task-worker.json \
    | sed -e "s/TAG/$TAG/g" \
    | sed -e "s/%RABBIT_HOST%/$RABBIT_HOST/g" \
    | sed -e "s/ECS_WORKER_SERVICE_ATTRIBUTE/$ECS_WORKER_SERVICE_ATTRIBUTE/g" \
    | sed -e "s/%NFS_OPTIONS%/$NFS_OPTIONS/g" \
    > docker/task-worker.tmp
  aws ecs register-task-definition --family "loyalty-$ECS_WORKER_SERVICE_ATTRIBUTE" --cli-input-json "file://docker/task-worker.tmp"
  docker/prod/prepare-ecs-task.py --source-task-family loyalty-$ECS_WORKER_SERVICE_ATTRIBUTE --target-task-family task
fi

if [[ "$run_migrations" == "true" ]]; then
  docker/prod/prepare-ecs-task.py --source-task-family loyalty-$ECS_WORKER_SERVICE_ATTRIBUTE --target-task-family migrations-$ECS_WORKER_SERVICE_ATTRIBUTE
  docker/prod/run-ecs-task.py --cluster $CLUSTER_NAME --task-family migrations-$ECS_WORKER_SERVICE_ATTRIBUTE --container worker --command 'bin/console aw:mongo-indexes -vv'
fi

if [[ "$deploy_workers" == "true" ]]; then
  aws ecs update-service --service $WORKERS_SERVICE_NAME --cluster $CLUSTER_NAME --task-definition loyalty-$ECS_WORKER_SERVICE_ATTRIBUTE
fi

if [[ "$deploy_web" == "true" ]]; then
  cat docker/prod/task-web.json \
    | sed -e "s/TAG/$TAG/g" \
    | sed -e "s/%RABBIT_HOST%/$RABBIT_HOST/g" \
    | sed -e "s/ECS_WEB_SERVICE_ATTRIBUTE/$ECS_WEB_SERVICE_ATTRIBUTE/g" \
    | sed -e "s/%NFS_OPTIONS%/$NFS_OPTIONS/g" \
    > docker/task-web.tmp
  aws ecs register-task-definition --family "loyalty-web" --cli-input-json "file://docker/task-web.tmp"
  aws ecs update-service --service $WEB_SERVICE_NAME --cluster $CLUSTER_NAME --task-definition loyalty-web
fi

if [[ "$wait" == "true" ]]; then
  mkdir -p build
  if [[ "$deploy_workers" == "true" && "$deploy_web" == "true" && "$config" == "prod" ]]; then
    echo aw-loyalty-builder >build/active_image
  fi

  if [[ "$deploy_workers" == "true" ]]; then
    docker/prod/wait-ecs-service.py $CLUSTER_NAME $WORKERS_SERVICE_NAME
    if [[ "$config" == "prod" ]]; then
      echo 718278292471.dkr.ecr.us-east-1.amazonaws.com/loyalty:worker-$TAG >>build/active_image
    fi
  fi

  if [[ "$deploy_web" == "true" ]]; then
    if [[ "$config" == "prod" ]]; then
      echo $REGISTRY/loyalty:fpm-$TAG >>build/active_image
    fi
    docker/prod/wait-ecs-service.py $CLUSTER_NAME $WEB_SERVICE_NAME
  fi
fi

echo SUCCESS, BUILT
