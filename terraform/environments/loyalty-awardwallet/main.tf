terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "~> 3.47"
    }
  }

  backend "s3" {
    bucket = "aw-configs"
    key = "loyalty-awardwallet-terraform-state"
    region = "us-east-1"
  }

  required_version = ">= 0.14.9"
}

provider "aws" {
  profile = "default"
  region = "us-east-1"
}

locals {
  mysql_instance_id = "i-074eb2b7cc037d657"
  project_name = "loyalty"
  vpc_id = "vpc-01342366"
  admin_security_group_id = "sg-e2499991"
  subnet_id = "subnet-5e800917"
  keypair_name = "ansible3"
  loyalty_security_group_id = "sg-0ae819ba2c5c65619"
  admin_cidr_block = ["192.168.2.166/32", "192.168.2.104/32"] # builder and admingate
  infra_zone_id = "Z02320123CC7MV7F2FEWO"
  vpn_security_group_id = "sg-256ca76f"
  nat_b_subnet_id = "subnet-0b66f36e4b8f3812a"
  nat_a_subnet_id = "subnet-071f715fac27a36c9"
  elasticsearch_security_group_id = "sg-08c27cb76112eaa6a"
  frontend_web_security_group_id = "sg-d55cb6a8"
}

data "aws_caller_identity" "awardwallet" {
}

data "aws_ami" "ecs_based" {
  most_recent = true
  filter {
    name = "name"
    values = ["ecs-based-*"]
  }
  owners = ["self"]
}

data "aws_route53_zone" "infra" {
  name = "infra.awardwallet.com."
  private_zone = true
}

data "aws_route53_zone" "awardwallet-com" {
  name     = "awardwallet.com."
}

data "aws_ssm_parameter" "jenkins_api_key" {
  name = "/config/jenkins_api_key"
  with_decryption = true
}

resource "aws_ecs_cluster" "main" {
  name = "loyalty"
}

resource "aws_service_discovery_private_dns_namespace" "loyalty" {
  name = "loyalty"
  vpc = local.vpc_id
}

module "monitoring" {
  source = "../../modules/monitoring"
  slack_channel = "aw_jenkins"
  host = "loyalty-rabbit"
  min_free_threads = 1
  max_undelivered_callbacks = 50
}

module "cpu-monitoring" {
  source = "../../modules/instance-monitoring"
  alarm_action = module.monitoring.alarm_action
  instances = {
    mysql = {
      id : local.mysql_instance_id
      threshold : 20
    }
  }
  project_name = local.project_name
}

module "cpu-credits-monitoring" {
  source = "../../modules/instance-monitoring"
  alarm_action = module.monitoring.alarm_action
  instances = {
    mysql = {
      id : local.mysql_instance_id
      threshold : 200
    }
  }
  project_name = local.project_name
  compare_operation = "LessThanOrEqualToThreshold"
  metric_name = "CPUCreditBalance"
}

module "wrapped-proxy" {
  source = "../../modules/wrapped-proxy"
  vpc_id = local.vpc_id
  allowed_security_group_ids = [local.loyalty_security_group_id, local.vpn_security_group_id, module.workers.workers_security_group_id, aws_security_group.selenium.id] # loyalty, vpn
  ssh_admin_security_group_id = local.admin_security_group_id # allow ssh from admingate
  ssh_keypair_name = local.keypair_name
  subnet_id = local.subnet_id # Main-B
  instance_type = "t3a.micro"
  infra_zone_id = local.infra_zone_id # infra.awardwallet.com
  public_zone_id = "ZSHEJPS7QDY6H" # awardwallet.com
  ami = data.aws_ami.ecs_based.id
  cluster_name = aws_ecs_cluster.main.name
  main_awardwallet_organization_id = data.aws_caller_identity.awardwallet.id
  ecs_cluster_id = aws_ecs_cluster.main.id
  empty_task_definition = "scratch"
  eip = "107.21.232.48" # whitelist-top-04
}

module "lpm" {
  source = "../../modules/lpm"
  vpc_id = local.vpc_id
  allowed_security_group_ids = [local.loyalty_security_group_id, local.vpn_security_group_id, module.workers.workers_security_group_id, aws_security_group.selenium.id] # loyalty
  ssh_admin_security_group_id = local.admin_security_group_id # allow ssh from admingate
  ssh_keypair_name = local.keypair_name
  subnet_id = local.subnet_id # Main-B
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

module "centrifugo" {
  source = "../../modules/centrifugo"
  vpc_id = local.vpc_id
  allowed_security_group_ids = [local.loyalty_security_group_id, aws_security_group.web.id, module.workers.workers_security_group_id]
  ssh_admin_security_group_id = local.admin_security_group_id # allow ssh from admingate
  ssh_keypair_name = local.keypair_name
  subnet_id = local.nat_b_subnet_id
  instance_type = "t4g.small"
  ami = data.aws_ami.ecs_based.id
  cluster_name = aws_ecs_cluster.main.name
  infra_zone_id = local.infra_zone_id
}

module "services" {
  source = "../../modules/services"
  vpc_id = local.vpc_id
  allowed_security_group_ids = [local.loyalty_security_group_id, module.workers.workers_security_group_id, aws_security_group.web.id]
  allowed_cidr_blocks = local.admin_cidr_block
  ssh_admin_security_group_id = local.admin_security_group_id
  ssh_keypair_name = local.keypair_name
  subnet_id = local.subnet_id
  memcached_instance_type = "t4g.micro"
  memcached_ami = "ami-0d296d66f22f256c2"
  files_ami = "ami-0bc08634af113cccb"
  files_instance_type = "t3a.small"
  mysql_ami = "ami-007868005aea67c54"
  mysql_instance_type = "t3a.micro"
  rabbitmq_ami = "ami-09f1f9580deeec186"
  rabbitmq_instance_type = "t4g.medium"
  mongo_ami = "ami-04d29b6f966df1537"
  mongo_node_instance_type = "t3a.small"
  mongo_arbiter_instance_type = "t3a.nano"
  infra_zone_id = local.infra_zone_id
  mongo_disk_size = 130
  prefix = "loyalty-awardwallet-"
  extra_files_security_group_ids = ["sg-d55cb6a8", "sg-0505edcc2db52d370"]
  extra_memcached_security_group_ids = ["sg-0ae819ba2c5c65619"]
  extra_rabbitmq_security_group_ids = ["sg-7ebbdf08", "sg-0ae819ba2c5c65619", "sg-10c40262"]
  extra_mysql_security_group_ids = ["sg-03e2c0e3be9bdfe9c", "sg-0ae819ba2c5c65619"]
  extra_mongo_security_group_ids = ["sg-088ef8d8e178dee6c", "sg-0ae819ba2c5c65619"]
}

resource "aws_security_group" "selenium" {
  name = "seleniums"
  description = "selenium servers"
  vpc_id = local.vpc_id
  egress {
    from_port = 0
    to_port = 0
    protocol = "-1"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    security_groups = [module.workers.workers_security_group_id]
    description = "allow selenium usage from loyalty workers"
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    security_groups = ["sg-0ae819ba2c5c65619"]
    description = ""
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    security_groups = ["sg-01273312bde730a4c"]
    description = "wsdl"
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    security_groups = ["sg-d55cb6a8"]
    description = "allow all from default vpc"
  }
  ingress {
    from_port = 22
    to_port = 22
    protocol = "tcp"
    security_groups = ["sg-37cc394a"]
    description = "allow ssh from admingate"
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    security_groups = ["sg-a06d08d3"]
    description = "allow selenium usage from ssh proxy"
  }
  ingress {
    from_port = 0
    to_port = 65535
    protocol = "tcp"
    cidr_blocks = ["172.30.0.0/16"]
    description = "from juicymiles"
  }
  ingress {
    from_port = 10000
    to_port = 65535
    protocol = "tcp"
    cidr_blocks = ["172.35.0.0/16"]
    description = "from ra-awardwallet"
  }
}

module "workers" {
  source = "../../modules/workers"
  ssh_keypair_name = local.keypair_name
  ecs_based_ami_id = data.aws_ami.ecs_based.image_id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
  empty_task_definition = "loyalty-workers:72"
  iam_role = "loyalty-instance"
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  ssh_admin_security_group_id = local.admin_security_group_id
  subnet_id = local.nat_b_subnet_id
  vpc_id = local.vpc_id
  on_demand_base_capacity = 1
  service_name = "workers-4"
  desired_capacity = 1
  min_healthy_percentage = 50
  min_instances = 1
  associate_public_ip_address = false
}

module "old-selenium" {
  source = "../../modules/old-selenium"
  instance_types = ["c5.xlarge", "c5d.xlarge", "c5a.xlarge", "c5ad.xlarge", "c5n.xlarge"]
  subnet_ids = [local.subnet_id]
  vpc_id = local.vpc_id
  on_demand_base_capacity = 0
  desired_capacity = 1
  min_healthy_percentage = 50
  min_instances = 1
  associate_public_ip_address = true
  asg_name = "old-selenium"
}

# module "proxy-white-list" {
#   source = "../../modules/proxy-white-list"
#   jenkins_api_key = data.aws_ssm_parameter.jenkins_api_key.value
# }
#
resource "aws_security_group_rule" "elasticsearch-fluentbit-workers" {
  from_port         = 24224
  protocol          = "tcp"
  security_group_id = local.elasticsearch_security_group_id
  to_port           = 24224
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "elasticsearch-es" {
  from_port         = 9200
  protocol          = "tcp"
  security_group_id = local.elasticsearch_security_group_id
  to_port           = 9200
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "shared-database" {
  from_port         = 3306
  protocol          = "tcp"
  security_group_id = "sg-03e2c0e3be9bdfe9c"
  to_port           = 3306
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "consul" {
  from_port         = 8500
  protocol          = "tcp"
  security_group_id = "sg-01f01f5bd6671d62a"
  to_port           = 8500
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "shared-memcached" {
  from_port         = 11211
  protocol          = "tcp"
  security_group_id = "sg-0bc5fbdafddc8c2d8"
  to_port           = 11211
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "whiteproxy" {
  from_port         = 3128
  protocol          = "tcp"
  security_group_id = "sg-097ce989e27ae3571"
  to_port           = 3128
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}

resource "aws_security_group_rule" "mail" {
  from_port         = 25
  protocol          = "tcp"
  security_group_id = "sg-0f4de6476e433393a"
  to_port           = 25
  type              = "ingress"
  source_security_group_id = module.workers.workers_security_group_id
}


# not using web module, because we have custom load balancer rules

resource "aws_security_group" "web" {
  name = "loyalty-web"
  description = "loyalty web instances"
  vpc_id = local.vpc_id
  egress {
    from_port = 0
    to_port = 0
    protocol = "-1"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
    description = "download anything"
  }
  ingress {
    from_port = 0
    to_port = 65535
    protocol = "tcp"
    security_groups = ["sg-eacb3e98"]
    description = "load balancers"
  }
}

resource "aws_security_group_rule" "elasticsearch-fluentbit" {
  from_port         = 24224
  protocol          = "tcp"
  security_group_id = local.elasticsearch_security_group_id
  to_port           = 24224
  type              = "ingress"
  source_security_group_id = aws_security_group.web.id
}

module "web" {
  source = "../../modules/ecs-service"
  vpc_id = local.vpc_id
  security_group_ids = [aws_security_group.web.id, local.admin_security_group_id]
  key-pair-name = local.keypair_name
  subnet_ids = [local.nat_b_subnet_id]
  ami_id = data.aws_ami.ecs_based.image_id
  ecs_cluster_id = aws_ecs_cluster.main.id
  ecs_cluster_name = aws_ecs_cluster.main.name
  snapshot_id = one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
  empty_task_definition = "loyalty-web:914"
  asg_name = "loyalty-web-2"
  balancers = [
    {
      container_name = "nginx"
      container_port = 80
      target_group_arn = "arn:aws:elasticloadbalancing:us-east-1:718278292471:targetgroup/loyalty/2d8a4b5600f0eda3"
    }
  ]
  iam_role_name = "loyalty-instance"
  instance_types = ["t3a.small"]
  on_demand_base_capacity = 2
  service_name = "web-2"
  service_registries = []
  associate_public_ip_address = false
}
