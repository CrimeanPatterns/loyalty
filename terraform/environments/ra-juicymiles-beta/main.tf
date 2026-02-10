terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "~> 3.47"
    }
  }

  backend "s3" {
    bucket = "aw-configs"
    key = "loyalty-juicymiles-beta-terraform-state"
    region = "us-east-1"
  }

  required_version = ">= 0.14.9"
}

provider "aws" {
  profile = "default"
  region = "us-east-1"
  assume_role {
    role_arn = "arn:aws:iam::${local.organization_id}:role/OrganizationAccountAccessRole"
  }
}

locals {
  organization_id = "288245819470"
  subnet_ids = ["subnet-0d65ca56116face3f"] // main
  vpc_id = "vpc-0b63946761ce38f95" # main
}

resource "aws_ecs_cluster" "main" {
  name = "beta"
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
  service_name = "workers"
  asg_name = "loyalty-workers-beta"
  iam_role_name = "loyalty-instance"
  security_group_ids = ["sg-087bd6effc865d2a5", "sg-0fb2da13abfed6ba1"] // workers, ssh-admin
  ami_id = data.aws_ami.ecs_based.id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
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
  key-pair-name = "main-ssh-key"
  associate_public_ip_address = true
  empty_task_definition = "loyalty-web:327"
  instance_types = ["t3a.medium"]
  on_demand_base_capacity = 2
  service_registries = []
}


