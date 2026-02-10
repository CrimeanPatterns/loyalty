#!/bin/sh

DIR="$( cd "$( dirname "$0" )" && pwd )"

#date=`date +%Y-%m-%d`
#file="logs.$date.tar"
#zip="$file.gz"

echo "Removing old stuff"

if [ -d "$DIR/../var/logs/check/tmp" ]; then
    echo "Cleaning old stuff in $DIR/../var/logs/check/tmp"
    cd "$DIR/../var/logs/check/tmp"
    find . -depth -mindepth 1 -mmin +180 -type d -print -execdir rm -R -f {} \;
else
    echo "Can't clean old stuff in $DIR/../var/logs/check/tmp"
fi

#for f in $(find /var/log/www/wsdlawardwallet/tmp/logs -type d -ctime +1 ); do 
#  rm -rf "$f"/;
#  echo $f;
#done

if [ -d "$DIR/../var/logs/check/checklogs" ]; then
    echo "Cleaning old stuff in $DIR/../var/logs/check/checklogs"
    cd $DIR/../var/logs/check/checklogs
    find . -mmin +180 -type f -print -delete
    find . -mmin +180 -type d -empty -print -delete
else
    echo "Can't clean old stuff in $DIR/../var/logs/check/checklogs"
fi

cd /tmp
echo "Cleaning old stuff in /tmp"
find . -mmin +90 -type f ! -path '*pear*' -print -delete
find . -type d -empty ! -path '*pear*' -print -delete
find . -path '*parser-log*' -depth -mindepth 1 -mmin +180 -type d -print -execdir rm -R -f {} \;
echo "done"
