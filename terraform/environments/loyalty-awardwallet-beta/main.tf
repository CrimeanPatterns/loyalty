terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "~> 3.47"
    }
  }

  backend "s3" {
    bucket = "aw-configs"
    key = "loyalty-awardwallet-beta-terraform-state"
    region = "us-east-1"
  }

  required_version = ">= 0.14.9"
}

provider "aws" {
  profile = "default"
  region = "us-east-1"
}

locals {
  ecs_cluster_id = "arn:aws:ecs:us-east-1:718278292471:cluster/loyalty"
  ecs_cluster_name = "loyalty"
  subnet_ids = ["subnet-5e800917"] // Main-B
  vpc_id = "vpc-01342366" # Main
}

data "aws_ami" "ecs_based" {
  most_recent = true
  filter {
    name = "name"
    values = ["ecs-based-*"]
  }
  owners = ["self"]
}

module "workers" {
  source = "../../modules/ecs-service"
  service_name = "workers-beta"
  asg_name = "loyalty-workers-beta"
  iam_role_name = "loyalty-instance"
  instance_type = "t3a.medium"
  security_group_ids = ["sg-0ae819ba2c5c65619"] // loyalty
  ecs_based_ami_id = data.aws_ami.ecs_based.id
  ecs_cluster_id = local.ecs_cluster_id
  ecs_cluster_name = local.ecs_cluster_name
  subnet_ids = local.subnet_ids
  vpc_id = local.vpc_id
  balancers = []
  instance_tags = {
    "proxy_access" = ""
  }
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  min_instances = 0
  min_healthy_percentage = 0
  desired_capacity = 1
  key-pair-name = "ansible3"
}


