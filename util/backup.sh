set -euxo pipefail

# full backup
dbName=wsdlawardwallet
backupFile="/backups/databases/loyalty.sql"
mysqldump --defaults-file=/var/lib/jenkins/vars/mysql/loyalty.cnf wsdlawardwallet --no-tablespaces --no-data > $backupFile
mysqldump --defaults-file=/var/lib/jenkins/vars/mysql/loyalty.cnf wsdlawardwallet --no-tablespaces --insert-ignore >> $backupFile

# lite backup
backupFile="/backups/databases/loyalty_lite.sql"
mysqldump --defaults-file=/var/lib/jenkins/vars/mysql/loyalty.cnf wsdlawardwallet --no-tablespaces --default-character-set=utf8  --single-transaction --no-data  > $backupFile"1" || exit 1
mysqldump --defaults-file=/var/lib/jenkins/vars/mysql/loyalty.cnf wsdlawardwallet --no-tablespaces --default-character-set=utf8 --single-transaction --ignore-table=$dbName.GeoTag  --ignore-table=$dbName.Fingerprint >> $backupFile"1"
mv $backupFile"1" $backupFile

# build lite database docker images
SCRIPT=`pwd`/util/build-loyalty.sql
cd ~/workspace/Docker/build-docker-images/mysql
CONTAINER_NAME=loyalty DUMP_SQL=$backupFile SCRIPT=$SCRIPT MYSQL_VERSION=5.7 ./build-data.sh
