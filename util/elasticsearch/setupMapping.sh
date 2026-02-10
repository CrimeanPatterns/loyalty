#!/usr/bin/env bash

# $1 - elasticsearch hostname

curl -XPUT 'http://'$1':9200/statistic/_mapping/partners' -d '
{
  "properties": {
    "Partner": {
      "type":     "text",
      "fielddata": true
    },
    "Provider": {
      "type":     "text",
      "fielddata": true
    },
    "UserID": {
      "type":     "text",
      "fielddata": true
    }
  }
}
'

echo "done"