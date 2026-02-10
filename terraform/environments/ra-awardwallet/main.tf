terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "~> 4.60"
    }
  }

  backend "s3" {
    bucket = "aw-configs"
    key = "ra-awardwallet-terraform-state"
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

provider "aws" {
  alias = "awardwallet"
  profile = "default"
  region = "us-east-1"
}

locals {
  organization_id = "732961821763"
  admin_cidr_block = ["192.168.2.166/32", "192.168.2.104/32"] # builder and admingate
  log_bucket_name = "ra-awardwallet-logs"
  cloudtrail_bucket_name = "ra-awardwallet-cloudtrail"
  ssm_path = "/loyalty/ra-awardwallet"
}

resource "random_password" "mysql_password" {
  length           = 20
  special          = false
}

resource "aws_ssm_parameter" "mysql_password" {
  name  = "${local.ssm_path}/mysql_password"
  type  = "SecureString"
  value = random_password.mysql_password.result
}

resource "random_password" "aes_key_local_browser_state" {
  length           = 32
  special          = false
}

resource "aws_ssm_parameter" "aes_key_local_browser_state" {
  name  = "${local.ssm_path}/aes_key_local_browser_state"
  type  = "SecureString"
  value = random_password.aes_key_local_browser_state.result
}

resource "random_password" "secret" {
  length           = 32
  special          = false
}

resource "aws_ssm_parameter" "secret" {
  name  = "${local.ssm_path}/secret"
  type  = "SecureString"
  value = random_password.secret.result
}

resource "aws_ecs_cluster" "main" {
  name = "main"
}

resource "aws_service_discovery_private_dns_namespace" "loyalty" {
  name = "loyalty"
  vpc = module.vpc.vpc_id
}

data "aws_ami" "ecs_based" {
  most_recent = true
  filter {
    name = "name"
    values = ["ecs-based-*"]
  }
  owners = ["self"]
}

data "aws_caller_identity" "awardwallet" {
  provider = aws.awardwallet
}

data "aws_vpc" "awardwallet-main" {
  provider = aws.awardwallet
  tags = {
    Name = "Main"
  }
}

module "vpc" {
  source = "../../modules/vpc"
  cidr_block_start = "172.35"
  peering_connections = {
    "awardwallet" = {
      cidr_block = "192.168.0.0/16"
      peer_owner_id = data.aws_caller_identity.awardwallet.id
      peer_vpc_id = data.aws_vpc.awardwallet-main.id
    }
    "webdriver" = {
      cidr_block = "172.34.0.0/16"
      peer_owner_id = "026052474993" # webdriver-cluster-2
      peer_vpc_id = "vpc-08dc4fe79ad66d6a4" # webdriver-cluster-2
    }
  }
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
  ssh_keypair_name = module.security.ssh_keypair_name
}

data "aws_ssm_parameter" "ssh-public-key" {
  name = "/config/ssh_public_key"
  provider = aws.awardwallet
}

module "security" {
  source = "../../modules/security"
  vpc_id = module.vpc.vpc_id
  ssh_admin_cidr_blocks = local.admin_cidr_block
  ssh_public_key = data.aws_ssm_parameter.ssh-public-key.value
  s3_log_bucket = local.log_bucket_name
  s3_cloudtrail_bucket = local.cloudtrail_bucket_name
  ssm_path = "/loyalty/ra-awardwallet"
}

module "empty-task-definition" {
  source = "../../modules/empty-task-definition"
}

module "selenium" {
  source = "../../modules/selenium"
  vpc_id = module.vpc.vpc_id
  allowed_security_group_ids = [module.workers.workers_security_group_id]
  allowed_cidr_blocks = local.admin_cidr_block
  consul_instance_type = "t4g.micro"
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
  ssh_keypair_name = module.security.ssh_keypair_name
  subnet_id = module.vpc.subnet_id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
  empty_task_definition = module.empty-task-definition.task_definition_id
  infra_zone_id = module.vpc.infra_zone_id
  min_healthy_percentage = 0
  min_instances = 1
  desired_healthy_servers = 1
}

data "aws_ami" "memcached" {
  most_recent = true
  filter {
    name = "name"
    values = ["memcached-*"]
  }
  owners = ["self"]
}
data "aws_ami" "mysql" {
  most_recent = true
  filter {
    name = "name"
    values = ["mysql"]
  }
  owners = ["self"]
}

data "aws_ami" "mongo" {
  most_recent = true
  filter {
    name = "name"
    values = ["mongo"]
  }
  owners = ["self"]
}

data "aws_ami" "rabbitmq" {
  most_recent = true
  filter {
    name = "name"
    values = ["rabbitmq"]
  }
  owners = ["self"]
}

data "aws_ami" "files" {
  most_recent = true
  filter {
    name = "name"
    values = ["files"]
  }
  owners = ["self"]
}

module "services" {
  source = "../../modules/services"
  vpc_id = module.vpc.vpc_id
  allowed_security_group_ids = [module.workers.workers_security_group_id, module.web.web_security_group_id]
  allowed_cidr_blocks = local.admin_cidr_block
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
  ssh_keypair_name = module.security.ssh_keypair_name
  subnet_id = module.vpc.subnet_id
  memcached_instance_type = "t4g.nano"
  files_instance_type = "t4g.micro"
  mysql_instance_type = "t3a.micro"
  rabbitmq_instance_type = "t4g.small"
  mongo_node_instance_type = "t3a.small"
  mongo_arbiter_instance_type = "t3a.small"
  infra_zone_id = module.vpc.infra_zone_id
  mongo_disk_size = 50
  memcached_ami = data.aws_ami.memcached.id
  mysql_ami = data.aws_ami.mysql.id
  mongo_ami = data.aws_ami.mongo.id
  rabbitmq_ami = data.aws_ami.rabbitmq.id
  files_ami = data.aws_ami.files.id
  prefix = ""
  associate_public_ip = false
}

# // not working, ignoring provider
#data "aws_route53_zone" "infra" {
#  name         = "infra.awardwallet.com."
#  vpc_id = data.aws_vpc.awardwallet-main.id
#  provider = aws.awardwallet
#  private_zone = true
#}

resource "aws_route53_record" "mysql" {
  zone_id = "Z02320123CC7MV7F2FEWO" # module.vpc.infra_zone_id
  name    = "mysql-ra-awardwallet.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [module.services.mysql_private_ip]
  provider = aws.awardwallet
}

module "autoscaling-ecs-hooks" {
  source = "../../modules/autoscaling-ecs-hooks"
  name = "loyalty"
  autoscaling_group_names = [module.selenium.asg_name]
  ecs_cluster_name = aws_ecs_cluster.main.name
}

data "aws_ssm_parameter" "jenkins_api_key" {
  name = "/config/jenkins_api_key"
  with_decryption = true
  provider = aws.awardwallet
}

module "proxy-white-list" {
  source = "../../modules/proxy-white-list"
  jenkins_api_key = data.aws_ssm_parameter.jenkins_api_key.value
}

#module "monitoring" {
#  source = "../../modules/monitoring"
#  slack_channel = "aw_jenkins"
#  queue_name = "loyalty_reward_availability_awardwallet"
#  min_free_threads = 1
#  max_undelivered_callbacks = 50
#}

data "aws_route53_zone" "awardwallet-com" {
    name     = "awardwallet.com."
    provider = aws.awardwallet
}

module "web" {
  source = "../../modules/web"
  providers = {
    aws = aws
    aws.dns = aws.awardwallet
  }
  ssh_keypair_name = module.security.ssh_keypair_name
  api_domain_name = "ra-flights.awardwallet.com"
  hotels_domain_name = "ra-hotels.awardwallet.com"
  ecs_based_ami_id = data.aws_ami.ecs_based.image_id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
  empty_task_definition = module.empty-task-definition.task_definition_id
  infra_zone_id = module.vpc.infra_zone_id
  iam_role = module.security.loyalty_instance_iam_role_name
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
  subnet_id = module.vpc.subnet_id
  other_subnet_id = module.vpc.other_subnet_id
  vpc_id = module.vpc.vpc_id
  dns_zone_id = data.aws_route53_zone.awardwallet-com.id
  on_demand_base_capacity = 0
  lb_subnet_ids = [module.vpc.nat-server-subnet-b, module.vpc.nat-server-subnet-a]
}

resource "aws_route53_record" "webdriver" {
  zone_id = module.vpc.infra_zone_id
  name    = "webdriver.infra.awardwallet.com"
  type    = "CNAME"
  ttl     = "30"
  records = ["internal-grid-245634075.us-east-1.elb.amazonaws.com"]
}

module "workers" {
  source = "../../modules/workers"
  ssh_keypair_name = module.security.ssh_keypair_name
  ecs_based_ami_id = data.aws_ami.ecs_based.image_id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
  empty_task_definition = module.empty-task-definition.task_definition_id
  iam_role = module.security.loyalty_instance_iam_role_name
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
  subnet_id = module.vpc.subnet_id
  vpc_id = module.vpc.vpc_id
  on_demand_base_capacity = 0
  asg_name = "workers"
  sg_name = "workers"
  associate_public_ip_address = false
}

#module "wrapped-proxy" {
#  source = "../../modules/wrapped-proxy"
#  vpc_id = module.vpc.vpc_id
#  allowed_security_group_ids = [module.workers.workers_security_group_id]
#  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id
#  ssh_keypair_name = module.security.ssh_keypair_name
#  subnet_id = module.vpc.subnet_id
#  instance_type = "t3a.micro"
#  infra_zone_id = module.vpc.infra_zone_id
#  public_zone_id = data.aws_route53_zone.awardwallet-com.zone_id
#  ami = data.aws_ami.ecs_based.id
#  cluster_name = aws_ecs_cluster.main.name
#  main_awardwallet_organization_id = data.aws_caller_identity.awardwallet.id
#  ecs_cluster_id = aws_ecs_cluster.main.id
#  empty_task_definition = module.empty-task-definition.task_definition_id
#}

module "lpm" {
  source = "../../modules/lpm"
  vpc_id = module.vpc.vpc_id
  allowed_security_group_ids = [module.workers.workers_security_group_id, module.selenium.security_group_id] # loyalty
  ssh_admin_security_group_id = module.security.ssh_admin_security_group_id # allow ssh from admingate
  ssh_keypair_name = module.security.ssh_keypair_name
  subnet_id = module.vpc.subnet_id # Main-B
  instance_type = "t3a.micro"
  ami = data.aws_ami.ecs_based.id
  cluster_name = aws_ecs_cluster.main.name
  main_awardwallet_organization_id = data.aws_caller_identity.awardwallet.id
  ecs_cluster_id = aws_ecs_cluster.main.id
  empty_task_definition = "scratch"
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  service_discovery_private_dns_namespace_id = aws_service_discovery_private_dns_namespace.loyalty.id
  allowed_cidr_blocks = ["172.34.0.0/16"]
}

