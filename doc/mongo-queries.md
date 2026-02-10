Queue info
----------
```mongojs
db.getCollection('CheckAccount').aggregate(
    {$match: {"response.state": 0, "queuedate": {$ne: null}, "response.checkDate": null}},
    {$project: {"partner":1, count: {$add: [1]}}},
    {$group: {_id: "$partner", number: {$sum: "$count"}}}
);         
```

Oldest account
----------
```mongojs
db.getCollection('CheckAccount').aggregate(
    {$project: {"response.checkDate":1}},
    {$group: {_id: null, max: {$min: "$response.checkDate"}}}
);         
```

