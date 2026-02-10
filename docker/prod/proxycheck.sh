#!/bin/sh
CHECKURL="https://s3.amazonaws.com/awardwallet-public/healthcheck.html"
CHECKPROXY="http://testproxy.awardwallet.com:3128"
CHECKSLEEP=10
TCOUNT=30
while [ $TCOUNT -gt 0 ]; do
    echo "Cheking availability of $CHECKURL with proxy $CHECKPROXY"
    if [ -n "$(curl -I --proxy "$CHECKPROXY" "$CHECKURL" 2>/dev/null | grep -F 'HTTP/1.1 200 OK' )" ]; then
        TCOUNT=-1
        break;
    else
        TCOUNT=$(( $TCOUNT - 1 ))
        echo "Check failed, countdown retries left: $TCOUNT"
    fi
    echo "Countdown: $TCOUNT"
    [ $TCOUNT -gt 0 ] && sleep "$CHECKSLEEP"
done
[ "$TCOUNT" -eq "0" ] && echo "All attempts is failed, exiting" && exit 127
exit 0
