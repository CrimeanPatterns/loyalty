#!/usr/bin/env python3

from boto3 import client
import logging as l
import argparse
import time
import signal
import sys
import os

l.basicConfig(format='%(message)s', level=l.INFO)

def parse_args():
    ap = argparse.ArgumentParser()
    ap.add_argument('--source-task-family', help='task definition family, based on which to run task', required=True)
    ap.add_argument('--target-task-family', help='task definition family, where to save target task definition', required=True)
    args = ap.parse_args()
    return args

def get_latest_task_definition(family):
    l.info("getting latest task definitions of {0}".format(family))
    response = ecs.list_task_definitions(familyPrefix=family, sort='DESC')

    if len(response['taskDefinitionArns']) == 0:
        l.error("no task definitions found in {0}".format(family))
        exit(2)

    task_definition_arn = response['taskDefinitionArns'][0]
    l.info("got task definition {0}".format(task_definition_arn))
    response = ecs.describe_task_definition(taskDefinition=task_definition_arn)

    return response['taskDefinition']

def prepare_task_definition(base_task_definition, target_task_family):
    for container in base_task_definition['containerDefinitions']:
        container['logConfiguration'] = {
            "logDriver": "awslogs",
            "options": {
                "awslogs-create-group": "true",
                "awslogs-group": "task",
                "awslogs-region": os.environ.get('AWS_DEFAULT_REGION', "us-east-1"),
                "awslogs-stream-prefix": "container"
            }
        }
        if container['name'] == 'worker':
            container['environment'].append({"name": "SYNC_ENGINE", "value": "0"})
            container['environment'].append({"name": "CHECK_PROXY_IP", "value": "0"})

    base_task_definition['containerDefinitions'] = list(filter(lambda container: (container['name'] not in ['squid', 'engine-sync', 'updater']),  base_task_definition['containerDefinitions']))

    base_task_definition['family'] = target_task_family
    del base_task_definition['taskDefinitionArn']
    del base_task_definition['revision']
    del base_task_definition['status']
    del base_task_definition['requiresAttributes']
    del base_task_definition['compatibilities']
    del base_task_definition['registeredAt']
    del base_task_definition['registeredBy']

    return base_task_definition


args = parse_args()
ecs = client('ecs')
logs = client('logs')

source_task_definition = get_latest_task_definition(args.source_task_family)
new_task_defintion = prepare_task_definition(source_task_definition, args.target_task_family)
response = ecs.register_task_definition(**new_task_defintion)
l.info("registered new task definition: {0}".format(response["taskDefinition"]["taskDefinitionArn"]))
