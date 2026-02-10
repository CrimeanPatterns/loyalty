#!/usr/bin/env python3

from boto3 import client
import logging as l
import argparse
import time
import signal
import sys

l.basicConfig(format='%(message)s', level=l.INFO)

def parse_args():
    ap = argparse.ArgumentParser()
    ap.add_argument('--cluster', help='cluster name, like main')
    ap.add_argument('--task-family', help='task definition family, based on which to run task', required=True)
    ap.add_argument('--container', required=True)
    ap.add_argument('--command', required=True)
    args = ap.parse_args()
    return args

def get_task(taskArn):
    tasks = ecs.describe_tasks(
        cluster=args.cluster,
        tasks=[ taskArn ]
    )["tasks"]

    if len(tasks) == 0:
        return None

    return tasks[0]

def wait_task_running(taskArn):
    l.info("waiting for task start")
    start_time = time.time()
    last_status = None
    report_time = start_time

    def signal_handler(sig, frame):
        print('stopping task {0}'.format(taskArn))
        ecs.stop_task(cluster=args.cluster, task=taskArn)
        sys.exit(1)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    while (time.time() - start_time) < 600:
        task = get_task(taskArn)
        if task is None:
            print("task not found yet")
            time.sleep(2)
            continue

        run_time = time.time() - start_time
        if last_status != task["lastStatus"] or (time.time() - report_time) > 30:
            last_status = task["lastStatus"]
            print("{0} .. {1}".format(task["lastStatus"], round(run_time)))
            report_time = time.time()
        if not task["lastStatus"] in ["PROVISIONING", "PENDING"]:
            return
        time.sleep(2)

    raise "Failed to wait for task start"

def task_complete(task):
    #l.info("task: {0}".format(task))
    if task["lastStatus"] in ["DEPROVISIONING", "STOPPED"]:
        failures = False
        for container in task["containers"]:
            if "reason" in container:
                l.info("container {0}: {1}".format(container["name"], container["reason"]))

            if not "exitCode" in container:
                l.info("container {0} has no exit code".format(container["name"]))
                failures = True
                continue

            if container["exitCode"] != 0:
                l.info("container {0} exited with error code: {1}".format(container["name"], container["exitCode"]))
                failures = True
                continue

        if not failures:
            l.info("task completed successfully")
        else:
            l.info("task failed")
            exit(1)
        return True

    return False

def show_task_logs(task, next_token, container):
    log_stream_name = "container/" + container + "/" + task["taskArn"].split("/")[-1]
    l.info("reading log stream {0}".format(log_stream_name))
    while True:
        if next_token is None:
            try:
                response = logs.get_log_events(
                    logGroupName="task",
                    logStreamName=log_stream_name,
                    startFromHead=True
                )
            except logs.exceptions.ResourceNotFoundException:
                print("no log stream yet: {0}".format(log_stream_name))
                return next_log_token
        else:
            response = logs.get_log_events(
                logGroupName="task",
                logStreamName=log_stream_name,
                startFromHead=True,
                nextToken=next_token
            )
        for event in response["events"]:
            print(event["message"])
        if response["nextForwardToken"] == next_token:
            break
        next_token = response["nextForwardToken"]
    return response["nextForwardToken"]

args = parse_args()
ecs = client('ecs')
logs = client('logs')

response = ecs.run_task(
    cluster=args.cluster,
    launchType="EC2",
    taskDefinition=args.task_family,
    overrides={
        "containerOverrides": [
            {
                "name": args.container,
                "command": args.command.split(" ")
            }
        ]
    }
)

if len(response["tasks"]) != 1:
    print(response)
    exit(1)

task = response["tasks"][0]
l.info("started task {0}".format(task["taskArn"]))
wait_task_running(task["taskArn"])

next_log_token = None
while True:
    task = get_task(task["taskArn"])
    next_log_token = show_task_logs(task, next_log_token, args.container)

    if task_complete(task):
        time.sleep(3) # wait for logs
        next_log_token = show_task_logs(task, next_log_token, args.container)
        break

    time.sleep(2)
