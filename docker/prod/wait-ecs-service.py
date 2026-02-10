#!/usr/bin/env python3

import boto3
import time
import argparse

def wait_for_deployment(cluster_name, service_name):
    ecs_client = boto3.client('ecs')

    while True:
        response = ecs_client.describe_services(
            cluster=cluster_name,
            services=[service_name]
        )

        if not response['services']:
            raise ValueError(f"No service found with name {service_name} in cluster {cluster_name}")

        service = response['services'][0]
        deployments = service.get('deployments', [])
        primary_deployment = None

        # Find the primary deployment
        for deployment in deployments:
            if deployment['status'] == 'PRIMARY':
                primary_deployment = deployment
                break

        if primary_deployment is None:
            raise ValueError(f"No primary deployment found for service {service_name}")

        # Check if all other deployments are complete
        all_others_completed = all(
            deployment['runningCount'] == 0 and deployment['pendingCount'] == 0
            for deployment in deployments
            if deployment != primary_deployment
        )

        if all_others_completed:
            print(f"Service {service_name} in cluster {cluster_name} has been fully deployed.")
            break

        print(f"Waiting for service {service_name} to be fully deployed...")
        time.sleep(15)

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Wait for ECS service to be fully deployed.")
    parser.add_argument("cluster_name", type=str, help="The name of the ECS cluster.")
    parser.add_argument("service_name", type=str, help="The name of the ECS service.")

    args = parser.parse_args()

    wait_for_deployment(args.cluster_name, args.service_name)
