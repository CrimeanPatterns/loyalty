#!/bin/sh

set -eu

LOCAL_USER_ID=$(id -u)
echo "started as user $LOCAL_USER_ID"
echo "SYMFONY_ENV: $SYMFONY_ENV"

if id "user"; then
    echo 'user found'
else
    echo 'user not found, creating'
    gosu root useradd --shell /bin/bash -u $LOCAL_USER_ID --no-create-home -G sudo -o -c "" user
fi

export HOME=/home/user

#if [ ! -f "/home/user/.ssh/known_hosts" ]; then
#  mkdir -p /home/user/.ssh
#  cp /opt/known_hosts /home/user/.ssh/
#  gosu chown user:user /home/user/.ssh/known_hosts
#fi

if [ -z "$*" ]; then
    echo "no command line, starting fpm"
    set -- php-fpm
    exec /usr/sbin/gosu root "$@"
else
    echo "running command $@"
    exec "$@"
fi

