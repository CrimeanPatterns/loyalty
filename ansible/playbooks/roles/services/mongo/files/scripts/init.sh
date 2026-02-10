#!/bin/bash

#DELAY=30

mongo < /tmp/scripts/replicaSetConfig.js

#echo "****** Waiting for ${DELAY} seconds for replicaset configuration to be applied ******"

#sleep $DELAY

#mongo < /scripts/initAuth.js