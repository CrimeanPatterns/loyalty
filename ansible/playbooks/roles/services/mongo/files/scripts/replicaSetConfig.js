var config = {
    "_id": "loyalty",
    "version": 1,
    "members": [
        {
            "_id": 1,
            "host": "mongo-1.infra.awardwallet.com:27017",
            "priority": 1
        },
        {
            "_id": 2,
            "host": "mongo-2.infra.awardwallet.com:27017",
            "priority": 1
        },
        {
            "_id": 3,
            "host": "mongo-arbiter.infra.awardwallet.com:27017",
            "arbiterOnly": true,
            "priority": 1
        }
    ]
};
rs.initiate(config, { force: true });
