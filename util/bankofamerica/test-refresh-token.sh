#!/usr/bin/env bash
curl \
    -v \
    --proxy host.docker.internal:3365 \
    --cert /usr/keys/awardwalletPremiumSslEv2020.pem \
    -H 'User-Agent: awardwallet' \
    -H 'Accept: application/json' \
    -H 'X-BOA-Session-ID: 12345' \
    -H 'X-BOA-Trace-ID: testCurl1' \
    --data 'client_id=123&client_secret=456&grant_type=refresh_token&refresh_token=789' \
    https://vendorservices.bankofamerica.com/apigateway/awardwallet/oauth/v1/boa/exchangeToken
