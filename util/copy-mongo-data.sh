#!/usr/bin/env bash

set -euxo pipefail

FROM=192.168.1.51
TO=172.30.14.115
DATABASE=juicymiles_ra

for COLLECTION in RaAccount BrowserState
do
  ssh $FROM "docker exec -t mongo_mongo_1 mongodump --quiet -d $DATABASE -c $COLLECTION --archive=/tmp/$COLLECTION.bson"
  ssh $FROM "docker cp mongo_mongo_1:/tmp/$COLLECTION.bson /tmp/"
  scp $FROM:/tmp/$COLLECTION.bson /tmp/
  scp /tmp/$COLLECTION.bson $TO:/tmp/
  ssh $TO "docker cp /tmp/$COLLECTION.bson mongo_mongo_1:/tmp/"
  ssh $TO "docker exec mongo_mongo_1 mongorestore -d $DATABASE --drop --archive=/tmp/$COLLECTION.bson"
done
