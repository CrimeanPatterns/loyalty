#!/usr/bin/env python3

from boto3 import client
from subprocess import check_call

print("getting current task definition")
ecs = client('ecs', region_name='us-east-1')
response = ecs.describe_services(cluster='loyalty', services=['workers-4'])
task_definition_arn = response['services'][0]['taskDefinition']
print("current task definition: {0}".format(task_definition_arn))

print("getting container version")
response = ecs.describe_task_definition(taskDefinition=task_definition_arn)
image = None
for container in response['taskDefinition']['containerDefinitions']:
    if container['name'] == 'worker':
        image = container['image']

if image is None:
    raise Exception("image not found")

print("current image: {0}".format(image))

def syntax_check():
    check_call(["docker", "run", "--rm", "-v", "/var/lib/jenkins/workspace/deploy-engine:/www/loyalty/current/src/AppBundle/Engine", "--entrypoint", "bin/syntax-check", image])

try:
    syntax_check()
except CalledProcessError as e:
    print("got exception {0}, no docker auth? will try to auth".format(str(e)))
    check_call(["bash", "-c", "aws ecr get-login --no-include-email | sh"])
    syntax_check()