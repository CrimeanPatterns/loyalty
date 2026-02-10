# ----- main config -----

terraform {
  required_providers {
    aws = {
      source = "hashicorp/aws"
      version = "~> 3.47"
    }
  }

  backend "s3" {
    bucket = "aw-configs"
    key = "ra-terraform-state"
    region = "us-east-1"
  }

  required_version = ">= 0.14.9"
}

provider "aws" {
  profile = "default"
  region = "us-east-1"
  assume_role {
    role_arn = "arn:aws:iam::${var.organization_id}:role/OrganizationAccountAccessRole"
  }
}

module "monitoring" {
  source = "../../modules/monitoring"
  slack_channel = "aw_reward_availability"
  host = "rabbitmq"
  max_undelivered_callbacks = 1000
  min_free_threads = 10
}

# ----- variables -----

variable "ansible_dir" {
  type = string
  default = "$(HOME)/ansible"
}

variable "organization_id" {
  type = string
  default = "288245819470"
}

variable "web_certificate_arn" {
  type = string
  default = "arn:aws:acm:us-east-1:288245819470:certificate/69d23a2d-557b-4e18-9705-6875827874ae"
}

variable "s3_log_bucket" {
  type = string
  default = "aw-loyalty-logs"
}

variable "partner_name" {
  type = string
  default = "juicymiles"
}

variable "builder_ip" {
  type = string
  default = "192.168.2.166"
}

variable "office_vpn_ip" {
  type = string
  default = "192.168.2.227"
}

variable "awardwallet_main_vpc_id" {
  type = string
  default = "vpc-01342366"
}

variable "awardwallet_main_account_id" {
  type = string
  default = "718278292471"
}

# ----- Data -----

data "aws_ssm_parameter" "jenkins_api_key" {
  name = "/config/jenkins_api_key"
  with_decryption = true
}

# ----- VPC -----

resource "aws_vpc" "main" {
  tags = {
    Name = "main"
  }
  cidr_block = "172.30.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support = true
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  tags = {
    Name = "main"
  }
}

resource "aws_subnet" "main" {
  vpc_id = aws_vpc.main.id
  cidr_block = "172.30.0.0/20"
  tags = {
    Name = "main"
  }
  availability_zone = "us-east-1b"
  map_public_ip_on_launch = true
}

resource "aws_subnet" "main_a" {
  vpc_id = aws_vpc.main.id
  cidr_block = "172.30.16.0/20"
  tags = {
    Name = "main-a"
  }
  availability_zone = "us-east-1a"
  map_public_ip_on_launch = true
}

resource "aws_subnet" "main_b" {
  vpc_id = aws_vpc.main.id
  cidr_block = "172.30.32.0/20"
  tags = {
    Name = "main-b"
  }
  availability_zone = "us-east-1b"
  map_public_ip_on_launch = true
}

resource "aws_egress_only_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  tags = {
    Name = "main"
  }
}

resource "aws_vpc_peering_connection" "to_awardwallet" {
  peer_owner_id = var.awardwallet_main_account_id
  peer_vpc_id   = var.awardwallet_main_vpc_id
  vpc_id        = aws_vpc.main.id
  tags = {
    Name = "awardwallet-${var.partner_name}"
  }
}

resource "aws_vpc_peering_connection" "to_awardwallet_webdriver_cluster" {
  peer_owner_id = "026052474993"
  peer_vpc_id   = "vpc-08dc4fe79ad66d6a4"
  vpc_id        = aws_vpc.main.id
  tags = {
    Name = "awardwallet-webdriver-cluster-${var.partner_name}"
  }
}

resource "aws_default_route_table" "main" {
  default_route_table_id = aws_vpc.main.default_route_table_id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  route {
    cidr_block = "192.168.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet.id
  }
  route {
    cidr_block = "172.34.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet_webdriver_cluster.id
  }
  tags = {
    Name = "main"
  }
}

resource "aws_route_table_association" "mfi_route_table_association" {
  subnet_id = aws_subnet.main.id
  route_table_id = aws_route_table.nat.id
}

resource "aws_subnet" "nat-server" {
  vpc_id = aws_vpc.main.id
  cidr_block = "172.30.128.0/20"
  tags = {
    Name = "nat-server-b"
  }
  availability_zone = "us-east-1b"
  map_public_ip_on_launch = true
}

resource "aws_route_table" "nat-server" {
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  route {
    cidr_block = "192.168.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet.id
  }
  route {
    cidr_block = "172.34.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet_webdriver_cluster.id
  }
  tags = {
    Name = "nat-server"
  }
  vpc_id = aws_vpc.main.id
}

resource "aws_route_table_association" "nat-server" {
  route_table_id = aws_route_table.nat-server.id
  subnet_id = aws_subnet.nat-server.id
}

module "nat-server" {
  source = "../../modules/nat-server"
  allowed_cidr_blocks = ["172.30.0.0/16"]
  ssh_admin_security_group_id = aws_security_group.ssh-admin.id
  ssh_keypair_name = aws_key_pair.main.key_name
  vpc_id = aws_vpc.main.id
  subnet_id = aws_subnet.nat-server.id
  instance_type = "t4g.small"
}

resource "aws_route_table" "nat" {
  route {
    cidr_block = "0.0.0.0/0"
    nat_gateway_id             = "nat-098ee1ef6b8913c9c"
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  route {
    cidr_block = "192.168.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet.id
  }
  route {
    cidr_block = "172.34.0.0/16"
    vpc_peering_connection_id = aws_vpc_peering_connection.to_awardwallet_webdriver_cluster.id
    carrier_gateway_id         = ""
    destination_prefix_list_id = ""
    egress_only_gateway_id     = ""
    gateway_id                 = ""
    instance_id                = ""
    ipv6_cidr_block            = ""
    local_gateway_id           = ""
    nat_gateway_id             = ""
    network_interface_id       = ""
    transit_gateway_id         = ""
    vpc_endpoint_id            = ""
  }
  tags = {
    Name = "nat"
  }
  vpc_id = aws_vpc.main.id
}

# ----- instance parameters -----

data "aws_ssm_parameter" "ssh-public-key" {
  name = "/config/ssh_public_key"
}

resource "aws_key_pair" "main" {
  key_name = "main-ssh-key"
  public_key = data.aws_ssm_parameter.ssh-public-key.value
}

# ----- ECS ------

resource "aws_iam_policy" "ecs_task_basic" {
  name = "ecs-task-basic"
  description = "ECS instance basic properties"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": [
                "arn:aws:ecr:*:718278292471:repository/postfix",
                "arn:aws:ecr:*:718278292471:repository/updater",
                "arn:aws:ecr:*:718278292471:repository/engine-sync",
                "arn:aws:ecr:*:718278292471:repository/fluent",
                "arn:aws:ecr:*:718278292471:repository/fluentbit",
                "arn:aws:ecr:*:718278292471:repository/amqproxy"
            ]
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
                "ecs:DiscoverPollEndpoint",
                "ec2:DescribeAddresses",
                "cloudwatch:PutMetricData",
                "ec2:DescribeTags",
                "ecs:CreateCluster",
                "ecs:UpdateContainerInstancesState",
                "s3:ListJobs",
                "ecr:GetAuthorizationToken",
                "ecs:RegisterContainerInstance",
                "ecs:Submit*",
                "s3:ListBucket",
                "cloudwatch:GetMetricStatistics",
                "logs:PutLogEvents",
                "ecs:Poll",
                "logs:CreateLogStream",
                "s3:GetAccountPublicAccessBlock",
                "ecs:StartTelemetrySession",
                "s3:ListAllMyBuckets",
                "cloudwatch:DescribeAlarms",
                "ec2:AssociateAddress",
                "ecs:DeregisterContainerInstance",
                "s3:HeadBucket"
            ],
            "Resource": "*"
        }
    ]
}
EOF
}

resource "aws_iam_policy_attachment" "attach-ecs" {
  name = "attach-ecs"
  roles = [aws_iam_role.loyalty_instance.name, aws_iam_role.selenium_instance.name]
  policy_arn = aws_iam_policy.ecs_task_basic.arn
}

resource "aws_ecs_cluster" "main" {
  name = "main"
}

# will be overwritten with deploy
resource "aws_ecs_task_definition" "scratch" {
  container_definitions = jsonencode([
    {
      name = "empty"
      image = "scratch"
      cpu = 10
      memory = 512
      essential = true
    }
  ])
  family = "service"
}

# ----- security groups ------

resource "aws_security_group" "ssh-admin" {
  name = "ssh-admin"
  description = "ssh access from jumphost and builder"
  vpc_id = aws_vpc.main.id
  ingress {
    from_port = 22
    to_port = 22
    protocol = "tcp"
    cidr_blocks = [
      "192.168.2.104/32",
      "192.168.2.166/32"
    ]
    description = "ssh from builder and admingate"
  }
  ingress {
    from_port = 3389
    to_port = 3389
    protocol = "tcp"
    cidr_blocks = [
      "192.168.2.104/32",
      "192.168.2.166/32"
    ]
    description = "rdp from builder and admingate"
  }
  ingress {
    from_port = 5901
    to_port = 5901
    protocol = "tcp"
    cidr_blocks = [
      "192.168.2.104/32",
      "192.168.2.166/32"
    ]
    description = "vnc from builder and admingate"
  }
  ingress {
    protocol = "icmp"
    cidr_blocks = [
      "192.168.2.104/32",
      "192.168.2.166/32"
    ]
    from_port = -1
    to_port = -1
    description = "ping from builder and admingate"
  }
}

# ----- consul -----

resource "aws_security_group" "consul" {
  name = "consul"
  description = "consul instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 8500
    to_port = 8500
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.selenium.id]
    description = "consul http api"
  }
  ingress {
    from_port = 8500
    to_port = 8500
    protocol = "tcp"
    cidr_blocks = [
      "${var.builder_ip}/32",
      "${var.office_vpn_ip}/32"
    ]
    description = "monitoring from builder and in-office seleniums"
  }
}

resource "aws_instance" "consul" {
  ami = data.aws_ami.base.id
  instance_type = "t3a.xlarge"
  key_name = aws_key_pair.main.key_name
  associate_public_ip_address = true
  subnet_id = aws_subnet.main.id
  iam_instance_profile = aws_iam_instance_profile.docker_instance.id
  disable_api_termination = false
  ebs_optimized = true
  hibernation = false
  monitoring = false
  vpc_security_group_ids = [
    aws_security_group.consul.id,
    aws_security_group.ssh-admin.id
  ]
  tags = {
    Name = "consul"
    services = ""
  }
}

# ----- memcached -----

resource "aws_security_group" "memcached" {
  name = "memcached"
  description = "memcached instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 11211
    to_port = 11211
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.web.id]
    description = "memcached api for workers"
  }
  ingress {
    from_port = 11211
    to_port = 11211
    protocol = "tcp"
    cidr_blocks = [
      "${var.builder_ip}/32"
    ]
    description = "memcached api for builder, to update dop proxy list"
  }
}

resource "aws_instance" "memcached" {
  ami = data.aws_ami.base.id
  instance_type = "t3a.large"
  key_name = aws_key_pair.main.key_name
  associate_public_ip_address = false
  subnet_id = aws_subnet.main.id
  vpc_security_group_ids = [
    aws_security_group.memcached.id,
    aws_security_group.ssh-admin.id
  ]
  tags = {
    Name = "memcached"
  }
  lifecycle {
    ignore_changes = [ami, user_data, timeouts]
  }
}

# ----- images -----

data "aws_ami" "ecs_based" {
  most_recent = true
  filter {
    name = "name"
    values = ["ecs-based-*"]
  }
  owners = ["self"]
}

data "aws_ami" "base" {
  most_recent = true
  filter {
    name = "name"
    values = ["base-*"]
  }
  owners = ["self"]
}

# ----- iam -----

resource "aws_iam_role" "docker_instance" {
  name = "docker-instance"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Effect": "Allow",
      "Sid": ""
    }
  ]
}
EOF
}

resource "aws_iam_policy" "ecr_read_services" {
  name = "ecr-read-services"
  description = "read service images from ecr"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "ecrRepos",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": [
                "arn:aws:ecr:*:718278292471:repository/postfix",
                "arn:aws:ecr:*:718278292471:repository/rabbitmq",
                "arn:aws:ecr:*:718278292471:repository/updater",
                "arn:aws:ecr:*:718278292471:repository/engine-sync",
                "arn:aws:ecr:*:718278292471:repository/fluent",
                "arn:aws:ecr:*:718278292471:repository/amqproxy",
                "arn:aws:ecr:*:718278292471:repository/proxyauth",
                "arn:aws:ecr:*:718278292471:repository/services/lpm",
                "arn:aws:ecr:*:718278292471:repository/squid-with-ssl"
            ]
        },
        {
            "Sid": "ecrAuth",
            "Effect": "Allow",
            "Action": [
                "ecr:GetAuthorizationToken"
            ],
            "Resource": "*"
        }
    ]
}
EOF
}

resource "aws_iam_policy" "cloudwatch_put_metrics" {
  name = "cloudwatch-put-metrics"
  description = "put cloudwtch metrics"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "putMetric",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:PutMetricData"
            ],
            "Resource": [
                "*"
            ]
        }
    ]
}
EOF
}

resource "aws_iam_instance_profile" "docker_instance" {
  name = "docker-instance"
  role = aws_iam_role.docker_instance.name
}

resource "aws_iam_policy_attachment" "docker-instance-attach-ecr" {
  name = "docker-ecr-attachment"
  roles = [aws_iam_role.docker_instance.name]
  policy_arn = aws_iam_policy.ecr_read_services.arn
}

resource "aws_iam_policy_attachment" "docker-instance-attach-put-metrics" {
  name = "docker-put-metrics-attachment"
  roles = [aws_iam_role.docker_instance.name]
  policy_arn = aws_iam_policy.cloudwatch_put_metrics.arn
}

resource "aws_iam_role" "loyalty_instance" {
  name = "loyalty-instance"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Effect": "Allow",
      "Sid": ""
    }
  ]
}
EOF
}

resource "aws_iam_policy" "loyalty_instance" {
  name = "loyalty-instance"
  description = "web or worker instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor0",
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:ListBucket",
                "ssm:GetParametersByPath",
                "ssm:GetParameters",
                "ssm:GetParameter"
            ],
            "Resource": [
                "arn:aws:s3:::${var.s3_log_bucket}/*",
                "arn:aws:s3:::${var.s3_log_bucket}",
                "arn:aws:ssm:*:${var.organization_id}:parameter/loyalty/prod/*",
                "arn:aws:ssm:*:${var.organization_id}:parameter/loyaty/prod",
                "arn:aws:ssm:*:${var.organization_id}:parameter/parsing/prod/*",
                "arn:aws:ssm:*:${var.organization_id}:parameter/parsing/prod"
            ]
        },
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
              "s3:PutObject",
              "s3:PutObjectAcl"
            ],
            "Resource": [
                "arn:aws:s3:::${var.s3_log_bucket}/${var.partner_name}*",
                "arn:aws:s3:::${var.s3_log_bucket}/awardwallet_checkaccount_testprovider_*",
                "arn:aws:s3:::${var.s3_log_bucket}/awardwallet_keephotsession_*",
                "arn:aws:s3:::${var.s3_log_bucket}/awardwallet_registeraccount_*"
            ]
        },
        {
            "Sid": "VisualEditor2",
            "Effect": "Allow",
            "Action": "cloudwatch:GetMetricStatistics",
            "Resource": "*"
        },
        {
            "Sid": "ECR",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": [
                "arn:aws:ecr:*:718278292471:repository/loyalty",
                "arn:aws:ecr:*:718278292471:repository/squid"
            ]
        }
    ]
}
EOF
}

resource "aws_iam_instance_profile" "loyalty_instance" {
  name = "loyalty-instance"
  role = aws_iam_role.loyalty_instance.name
}

resource "aws_iam_policy_attachment" "loyalty-instance-attach-loyalty" {
  name = "loyalty-loyalty-attachment"
  roles = [aws_iam_role.loyalty_instance.name]
  policy_arn = aws_iam_policy.loyalty_instance.arn
}

resource "aws_iam_policy" "asg_lifecycle_hook" {
  name = "asg-lifecycle-hook"
  description = "run lambda under this role after asg scale event"
  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "autoscaling:CompleteLifecycleAction"
      ],
      "Resource": "arn:aws:autoscaling:*:${var.organization_id}:autoScalingGroup:*:*"
    },
    {
        "Effect": "Allow",
        "Action": [
            "ssm:GetParameter"
        ],
        "Resource": [
            "arn:aws:ssm:*:${var.organization_id}:parameter/config/jenkins_api_key"
        ]
    }
  ]
}
EOF
}

resource "aws_iam_role" "asg_lifecycle_hook" {
  name = "asg-lifecycle-hook"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "lambda.amazonaws.com"
      },
      "Effect": "Allow"
    }
  ]
}
EOF
}

resource "aws_iam_policy_attachment" "asg-lifecycle-hook" {
  name = "asg-lifecycle-hook"
  roles = [aws_iam_role.asg_lifecycle_hook.name]
  policy_arn = aws_iam_policy.asg_lifecycle_hook.arn
}

resource "aws_iam_policy_attachment" "lambda-execution" {
  name = "lambda-execution"
  roles = [aws_iam_role.asg_lifecycle_hook.name]
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"
}

resource "aws_iam_policy" "eventbridge_invoke_api_destinations" {
  name = "eventbridge-invoke-api-destinations"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "events:InvokeApiDestination"
            ],
            "Resource": [
                "arn:aws:events:us-east-1:288245819470:api-destination/*"
            ]
        }
    ]
}
EOF
}

resource "aws_iam_role" "eventbridge_invoke_api_destinations" {
  name = "eventbridge-invoke-api-destinations"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "events.amazonaws.com"
      },
      "Effect": "Allow"
    }
  ]
}
EOF
}

resource "aws_iam_policy_attachment" "eventbridge_invoke_api_destinations" {
  name = "eventbridge-invoke-api-destinations"
  roles = [aws_iam_role.eventbridge_invoke_api_destinations.name]
  policy_arn = aws_iam_policy.eventbridge_invoke_api_destinations.arn
}

# ----- rabbit -----

resource "aws_security_group" "rabbit" {
  name = "rabbit"
  description = "RabbitMQ instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 5672
    to_port = 5672
    protocol = "tcp"
    # sg-0603cc55ac5079225 - mac puppeteer, rabbit mode
    security_groups = [aws_security_group.workers.id, aws_security_group.web.id, "sg-0603cc55ac5079225"]
    cidr_blocks = ["192.168.2.166/32"]
    description = "builder here for triggering engine update"
    self = true
  }
}

#resource "aws_instance" "rabbitmq" {
#  ami = data.aws_ami.base.id
#  instance_type = "t3a.small"
#  iam_instance_profile = aws_iam_instance_profile.docker_instance.id
#  key_name = aws_key_pair.main.key_name
#  associate_public_ip_address = true
#  subnet_id = aws_subnet.main.id
#  vpc_security_group_ids = [
#    aws_security_group.rabbit.id,
#    aws_security_group.ssh-admin.id
#  ]
#  tags = {
#    Name = "rabbitmq"
#  }
#}

# ----- mysql ------

resource "aws_security_group" "mysql" {
  name = "mysql"
  description = "mysql instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 3306
    to_port = 3306
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.web.id, aws_security_group.ssh-admin.id]
    description = "mysql"
  }
  ingress {
    from_port = 3306
    to_port = 3306
    protocol = "tcp"
    cidr_blocks = [
      "192.168.2.104/32",
      "192.168.2.166/32"
    ]
    description = "mysql from builder and admingate"
  }
}

# ----- selenium -----

data "aws_ami" "selenium" {
  most_recent = true
  filter {
    name = "name"
    values = ["selenium-*"]
  }
  filter {
    name = "virtualization-type"
    values = ["hvm"]
  }
  owners = ["self"]
}

resource "aws_iam_role" "selenium_instance" {
  name = "selenium-instance"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Effect": "Allow",
      "Sid": ""
    }
  ]
}
EOF
}

resource "aws_iam_policy" "selenium_instance" {
  name = "selenium-instance"
  description = "Selenium instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "VisualEditor1",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": "arn:aws:ecr:*:718278292471:repository/selenium2"
        }
    ]
}
EOF
}

resource "aws_iam_instance_profile" "selenium_instance" {
  name = "selenium-instance"
  role = aws_iam_role.selenium_instance.name
}

resource "aws_iam_policy_attachment" "selenium-instance-attach-selenium" {
  name = "selenium-selenium-attachment"
  roles = [aws_iam_role.selenium_instance.name]
  policy_arn = aws_iam_policy.selenium_instance.arn
}

resource "aws_security_group" "selenium" {
  name = "selenium"
  description = "selenium instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 10000
    to_port = 65000
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id]
    description = "different browsers on multiple selenium ports"
  }
}

resource "aws_launch_template" "selenium" {
  name = "selenium"
  update_default_version = true
  image_id = data.aws_ami.selenium.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      proxy_access = ""
      Name = "selenium"
    }
  }
  iam_instance_profile {
    name = "selenium-instance"
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    http_protocol_ipv6          = "enabled"
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = false
    security_groups = [aws_security_group.selenium.id, aws_security_group.ssh-admin.id]
  }
  block_device_mappings {
    device_name = "/dev/xvda"
    ebs {
      delete_on_termination = true
      snapshot_id = one(data.aws_ami.selenium.block_device_mappings).ebs.snapshot_id
      volume_size = 30
      volume_type = "gp3"
      throughput = 125
    }
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=main" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"selenium\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
echo "ECS_ENGINE_TASK_CLEANUP_WAIT_DURATION=5m" >>/etc/ecs/ecs.config
echo "ECS_IMAGE_CLEANUP_INTERVAL=5m" >>/etc/ecs/ecs.config
echo "ECS_IMAGE_MINIMUM_CLEANUP_AGE=5m" >>/etc/ecs/ecs.config
echo "ECS_NUM_IMAGES_DELETE_PER_CYCLE=20" >>/etc/ecs/ecs.config
EOF
  )
  lifecycle {ignore_changes = [image_id, block_device_mappings]}
}

resource "aws_autoscaling_group" "seleniums" {
  name = "seleniums"
  desired_capacity = 200
  max_size = 200
  min_size = 20
  capacity_rebalance = false
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.selenium.id
      }
      override {
        instance_type = "m5a.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t3a.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t3.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t2.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m3.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m4.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m5.xlarge"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 200
      spot_allocation_strategy = "capacity-optimized"
      spot_instance_pools = null
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 0 # todo: change to 80 in production
    }
  }
  enabled_metrics = [
    "GroupDesiredCapacity",
    "GroupInServiceInstances",
    "GroupPendingInstances",
    "GroupTotalInstances",
  ]
  depends_on = [
    aws_subnet.main,
    aws_security_group.selenium,
    aws_security_group.ssh-admin
  ]
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_autoscaling_policy" "seleniums-scale-up" {
  name                   = "seleniums-scale-up"
  scaling_adjustment     = 10
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 90
  autoscaling_group_name = aws_autoscaling_group.seleniums.name
}

resource "aws_cloudwatch_metric_alarm" "seleniums-scale-up" {
  alarm_name          = "seleniums-scale-up"
  comparison_operator = "LessThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "healthy-selenium-servers"
  namespace           = "AW/Loyalty"
  period              = "10"
  statistic           = "Minimum"
  threshold           = "40"
  alarm_description = "scale seleniums up when there are no free servers"
  alarm_actions     = [aws_autoscaling_policy.seleniums-scale-up.arn]
}

resource "aws_autoscaling_policy" "seleniums-scale-down" {
  name                   = "seleniums-scale-down"
  scaling_adjustment     = -5
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 120
  autoscaling_group_name = aws_autoscaling_group.seleniums.name
}

resource "aws_cloudwatch_metric_alarm" "seleniums-scale-down" {
  alarm_name          = "seleniums-scale-down"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "20"
  metric_name         = "healthy-selenium-servers"
  namespace           = "AW/Loyalty"
  period              = "60"
  statistic           = "Minimum"
  threshold           = "60"
  alarm_description = "scale seleniums down when there are too much free servers"
  alarm_actions     = [aws_autoscaling_policy.seleniums-scale-down.arn]
}

resource "aws_ecs_service" "seleniums" {
  name = "seleniums"
  cluster = aws_ecs_cluster.main.id
  scheduling_strategy = "DAEMON"
  # will be overridden with deploy-selenium-monitor
  task_definition = aws_ecs_task_definition.scratch.arn
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == selenium"
  }
  deployment_minimum_healthy_percent = 50
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition]
  }
}

# ----- selenium-beta -----

resource "aws_launch_template" "selenium-beta" {
  name = "selenium-beta"
  update_default_version = true
  image_id = data.aws_ami.selenium.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      proxy_access = ""
      Name = "selenium"
    }
  }
  iam_instance_profile {
    name = "selenium-instance"
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    http_protocol_ipv6          = "enabled"
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = false
    security_groups = [aws_security_group.selenium.id, aws_security_group.ssh-admin.id]
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=main" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"selenium-beta\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
EOF
  )
  lifecycle {ignore_changes = [image_id]}
}

resource "aws_autoscaling_group" "seleniums-beta" {
  name = "seleniums-beta"
  desired_capacity = 1
  max_size = 1
  min_size = 0
  capacity_rebalance = true
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.selenium-beta.id
      }
      override {
        instance_type = "m5a.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t3a.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t3.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t2.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m3.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m4.xlarge"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m5.xlarge"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 1
      spot_allocation_strategy = "capacity-optimized"
      spot_instance_pools = null
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 0 # todo: change to 80 in production
    }
  }
  depends_on = [
    aws_subnet.main,
    aws_security_group.selenium,
    aws_security_group.ssh-admin
  ]
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_ecs_service" "seleniums-beta" {
  name = "seleniums-beta"
  cluster = aws_ecs_cluster.main.id
  scheduling_strategy = "REPLICA"
  desired_count = 1
  # will be overridden with deploy-selenium-monitor
  task_definition = "selenium2:10"
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == selenium-beta"
  }
  deployment_minimum_healthy_percent = 0
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition, desired_count]
  }
}

# ----- web -----

resource "aws_security_group" "web_load_balancers" {
  name = "web-load-balancers"
  description = "alb web load balancers"
  vpc_id = aws_vpc.main.id
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
    from_port = 443
    to_port = 443
    protocol = "tcp"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
    description = "public https"
  }
}

resource "aws_lb" "web" {
  name               = "web"
  security_groups    = [aws_security_group.web_load_balancers.id]
  subnets            = [aws_subnet.nat-server.id, aws_subnet.main_a.id]
}

resource "aws_lb_target_group" "web" {
  name     = "web"
  port     = 80
  protocol = "HTTP"
  vpc_id   = aws_vpc.main.id
  health_check {
    healthy_threshold = 2
    unhealthy_threshold = 2
    timeout = 20
    interval = 30
    path = "/elb-check"
  }
  deregistration_delay = 30
}

resource "aws_lb_listener" "web" {
  load_balancer_arn = aws_lb.web.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = var.web_certificate_arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.web.arn
  }
}

resource "aws_security_group" "web" {
  name = "web"
  description = "web instances"
  vpc_id = aws_vpc.main.id
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
    security_groups = [aws_security_group.web_load_balancers.id]
    description = "load balancers"
  }
}

resource "aws_launch_template" "web" {
  name = "web"
  update_default_version = true
  image_id = data.aws_ami.ecs_based.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "web"
    }
  }
  iam_instance_profile {
    name = "loyalty-instance"
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    http_protocol_ipv6          = "enabled"
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = false
    security_groups = [aws_security_group.web.id, aws_security_group.ssh-admin.id]
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=main" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"web\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
EOF
  )
}

resource "aws_autoscaling_group" "web" {
  name = "web"
  desired_capacity = 8
  max_size = 20
  min_size = 2
  capacity_rebalance = false
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.web.id
      }
      override {
        instance_type = "t3a.small"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t3.small"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t2.small"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 2
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 50 # todo: change to 80 in production
    }
  }
}

# will be overwritten with deploy
resource "aws_ecs_task_definition" "web" {
  container_definitions = jsonencode([
    {
      name = "nginx"
      image = "scratch"
      cpu = 10
      memory = 512
      essential = true
      portMappings = [
        {
          protocol = "tcp",
          containerPort = 80,
          hostPort = 0
        }
      ]
    }
  ])
  family = "service"
}

resource "aws_ecs_service" "web" {
  name = "web"
  cluster = aws_ecs_cluster.main.id
  scheduling_strategy = "DAEMON"
  task_definition = aws_ecs_task_definition.web.arn
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == web"
  }
  deployment_minimum_healthy_percent = 50 # TODO change to 50 in prod
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition]
  }
  load_balancer {
    container_name = "nginx"
    container_port = 80
    target_group_arn = aws_lb_target_group.web.arn
  }
}

# ----- workers -----

resource "aws_security_group" "workers" {
  name = "workers"
  description = "worker instances"
  vpc_id = aws_vpc.main.id
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
}

resource "aws_security_group" "white-proxy" {
  name = "white-proxy"
  description = "white proxy instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 3128
    to_port = 3128
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id]
    description = "allow proxy access from workers"
  }
}

resource "aws_launch_template" "worker" {
  name = "worker"
  update_default_version = true
  image_id = data.aws_ami.ecs_based.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      proxy_access = ""
      Name = "worker"
    }
  }
  iam_instance_profile {
    name = "loyalty-instance"
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    http_protocol_ipv6          = "enabled"
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = false
    security_groups = [aws_security_group.workers.id, aws_security_group.ssh-admin.id]
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=main" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"workers\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
EOF
  )
}

resource "aws_autoscaling_group" "workers" {
  name = "workers"
  desired_capacity = 50
  max_size = 80
  min_size = 10
  capacity_rebalance = false
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.worker.id
      }
      override {
        instance_type = "t3a.large"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m5a.large"
        weighted_capacity = "1"
      }
      override {
        instance_type = "m5.large"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 50
    }
  }
  enabled_metrics = [
    "GroupDesiredCapacity",
    "GroupInServiceInstances",
    "GroupPendingInstances",
    "GroupTotalInstances",
  ]
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 50 # todo: change to 80 in production
    }
  }
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_cloudwatch_event_rule" "asg_lifecycle_action" {
  name        = "asg-lifecycle-action"
  description = "used to fire lambda after asg scale event"

  event_pattern = <<EOF
{
  "source": [ "aws.autoscaling" ],
  "detail-type": [ "EC2 Instance-launch Lifecycle Action" ]
}
EOF
}

resource "aws_autoscaling_policy" "workers-scale-up" {
  name                   = "workers-scale-up"
  scaling_adjustment     = 10
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 90
  autoscaling_group_name = aws_autoscaling_group.workers.name
}

resource "aws_cloudwatch_metric_alarm" "workers-scale-up" {
  alarm_name          = "workers-scale-up"
  comparison_operator = "LessThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "free_threads"
  namespace           = "AW/Loyalty"
  period              = "10"
  statistic           = "Minimum"
  threshold           = "500"
  alarm_description = "scale workers up when there are no free threads"
  alarm_actions     = [aws_autoscaling_policy.workers-scale-up.arn]
}

resource "aws_autoscaling_policy" "workers-scale-down" {
  name                   = "workers-scale-down"
  scaling_adjustment     = -5
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 120
  autoscaling_group_name = aws_autoscaling_group.workers.name
}

resource "aws_cloudwatch_metric_alarm" "workers-scale-down" {
  alarm_name          = "workers-scale-down"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "5"
  metric_name         = "free_threads"
  namespace           = "AW/Loyalty"
  period              = "60"
  statistic           = "Minimum"
  threshold           = "600"
  alarm_description = "scale workers down when there are too much free threads"
  alarm_actions     = [aws_autoscaling_policy.workers-scale-down.arn]
}

//data "archive_file" "lambda_zip" {
//    type        = "zip"
//    source_dir  = "lambda"
//    output_path = "lambda.zip"
//}
//
//resource "aws_lambda_function" "update_proxy_white_list" {
//  filename      = "lambda.zip"
//  source_code_hash = "${data.archive_file.lambda_zip.output_base64sha256}"
//  function_name = "update_proxy_white_list"
//  handler       = "update-proxy-white-list.lambda_handler"
//  role          = aws_iam_role.asg_lifecycle_hook.arn
//  runtime = "python3.8"
//  timeout = 30
//}

resource "aws_cloudwatch_event_connection" "jenkins" {
  name               = "jenkins"
  authorization_type = "BASIC"
  auth_parameters {
    basic {
      username = "jenkins"
      password = data.aws_ssm_parameter.jenkins_api_key.value
    }
  }
}

resource "aws_cloudwatch_event_api_destination" "jenkins_update_proxy_white_list" {
  name                             = "jenkins-update-proxy-white-list"
  description                      = "update proxy white list job"
  invocation_endpoint              = "https://jenkins.awardwallet.com/job/Loyalty/job/update-proxy-white-list/build"
  http_method                      = "POST"
  invocation_rate_limit_per_second = 5
  connection_arn                   = aws_cloudwatch_event_connection.jenkins.arn
}

resource "aws_cloudwatch_event_target" "asg_lifecycle_action" {
  arn  = "${aws_cloudwatch_event_api_destination.jenkins_update_proxy_white_list.arn}"
  rule = aws_cloudwatch_event_rule.asg_lifecycle_action.id
  role_arn = aws_iam_role.eventbridge_invoke_api_destinations.arn
}

resource "aws_autoscaling_lifecycle_hook" "worker_added" {
  name                   = "worker_added"
  autoscaling_group_name = aws_autoscaling_group.workers.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 60
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_LAUNCHING"
}

resource "aws_autoscaling_lifecycle_hook" "worker_terminated" {
  name                   = "worker_terminated"
  autoscaling_group_name = aws_autoscaling_group.workers.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 60
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_TERMINATING"
}

resource "aws_autoscaling_lifecycle_hook" "selenium_added" {
  name                   = "selenium_added"
  autoscaling_group_name = aws_autoscaling_group.seleniums.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 60
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_LAUNCHING"
}

resource "aws_autoscaling_lifecycle_hook" "selenium_terminated" {
  name                   = "selenium_terminated"
  autoscaling_group_name = aws_autoscaling_group.seleniums.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 60
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_TERMINATING"
}

resource "aws_ecs_service" "workers" {
  name = "workers"
  cluster = aws_ecs_cluster.main.id
  scheduling_strategy = "DAEMON"
  task_definition = aws_ecs_task_definition.scratch.arn
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == workers"
  }
  deployment_minimum_healthy_percent = 50
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition]
  }
}

# ------ workers beta ------

data "aws_ecs_task_definition" "worker" {
  task_definition = "loyalty-worker"
}

# ------ web beta ------

resource "aws_lb_target_group" "web-beta" {
  name     = "web-beta"
  port     = 80
  protocol = "HTTP"
  vpc_id   = aws_vpc.main.id
  health_check {
    healthy_threshold = 2
    unhealthy_threshold = 2
    timeout = 20
    interval = 30
    path = "/elb-check"
  }
  deregistration_delay = 5
}

resource "aws_launch_template" "web-beta" {
  name = "web-beta"
  update_default_version = true
  image_id = data.aws_ami.ecs_based.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "web-beta"
    }
  }
  iam_instance_profile {
    name = "loyalty-instance"
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    http_protocol_ipv6          = "enabled"
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = false
    security_groups = [aws_security_group.web.id, aws_security_group.ssh-admin.id]
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=main" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"web-beta\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
EOF
  )
}

resource "aws_autoscaling_group" "web-beta" {
  name = "web-beta"
  desired_capacity = 0
  max_size = 1
  min_size = 0
  capacity_rebalance = true
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.web-beta.id
      }
      override {
        instance_type = "t3a.small"
        weighted_capacity = "1"
      }
      override {
        instance_type = "t2.small"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 1
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 0
    }
  }
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_ecs_service" "web-beta" {
  name = "web-beta"
  cluster = aws_ecs_cluster.main.id
  scheduling_strategy = "REPLICA"
  desired_count = 1
  task_definition = aws_ecs_task_definition.web.arn
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == web-beta"
  }
  deployment_minimum_healthy_percent = 0 # TODO change to 50 in prod
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition, desired_count]
  }
  load_balancer {
    container_name = "nginx"
    container_port = 80
    target_group_arn = aws_lb_target_group.web-beta.arn
  }
}

resource "aws_lb_listener_rule" "beta" {
  listener_arn = aws_lb_listener.web.arn
  priority = 100
  action {
    type = "forward"
    target_group_arn = aws_lb_target_group.web-beta.arn
  }
  condition {
    host_header {
      values = ["beta.jm.awardwallet.com"]
    }
  }
}

# ------ lpm ------

resource "aws_launch_template" "lpm" {
  name = "lpm"
  update_default_version = true
  image_id = data.aws_ami.base.id
  key_name = aws_key_pair.main.key_name
  tag_specifications {
    resource_type = "instance"
    tags = {
      Name = "lpm"
      proxy_access = ""
    }
  }
  iam_instance_profile {
    name = aws_iam_instance_profile.docker_instance.name
  }
  credit_specification {
    cpu_credits = "standard"
  }
  ebs_optimized = true
  network_interfaces {
    subnet_id = aws_subnet.main.id
    associate_public_ip_address = true
    security_groups = [aws_security_group.lpm.id, aws_security_group.ssh-admin.id]
  }
}

resource "aws_autoscaling_group" "lpm" {
  name = "lpm"
  desired_capacity = 1
  max_size = 2
  min_size = 1
  capacity_rebalance = true
  vpc_zone_identifier = [aws_subnet.main.id]
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.lpm.id
      }
      override {
        instance_type = "t3a.large"
        weighted_capacity = "1"
      }
    }
    instances_distribution {
      on_demand_base_capacity = 1
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = 50 # todo: change to 80 in production
    }
  }
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_security_group" "lpm" {
  name = "lpm"
  description = "lpm instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 3128
    to_port = 3128
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.selenium.id]
    description = "squid"
  }
  ingress {
    from_port = 22225
    to_port = 22225
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.selenium.id]
    description = "lpm main port"
  }
  ingress {
    from_port = 24000
    to_port = 64000
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.selenium.id, data.aws_security_group.vpn-servers.id]
    description = "lpm additional ports"
  }
}

# ------ machine images -----

resource "aws_s3_bucket" "machine-images" {
  bucket = "machine-images-${var.partner_name}"
  acl    = "private"
  server_side_encryption_configuration {
    rule {
      apply_server_side_encryption_by_default {
        sse_algorithm     = "aws:kms"
      }
    }
  }
}

resource "aws_iam_role" "vmimport" {
  name = "vmimport"
  assume_role_policy = <<EOF
{
   "Version": "2012-10-17",
   "Statement": [
      {
         "Effect": "Allow",
         "Principal": { "Service": "vmie.amazonaws.com" },
         "Action": "sts:AssumeRole",
         "Condition": {
            "StringEquals":{
               "sts:Externalid": "vmimport"
            }
         }
      }
   ]
}
EOF
}

resource "aws_iam_policy" "upload-machine-images" {
  name = "upload-machine-images"
  description = "upload AMI"
  policy = <<EOF
{
   "Version":"2012-10-17",
   "Statement":[
      {
         "Effect": "Allow",
         "Action": [
            "s3:GetBucketLocation",
            "s3:GetObject",
            "s3:ListBucket"
         ],
         "Resource": [
            "arn:aws:s3:::${aws_s3_bucket.machine-images.bucket}",
            "arn:aws:s3:::${aws_s3_bucket.machine-images.bucket}/*"
         ]
      },
      {
         "Effect": "Allow",
         "Action": [
            "ec2:ModifySnapshotAttribute",
            "ec2:CopySnapshot",
            "ec2:RegisterImage",
            "ec2:Describe*"
         ],
         "Resource": "*"
      }
   ]
}
EOF
}

resource "aws_iam_policy_attachment" "upload-machine-images-attach" {
  name = "upload-machine-images"
  roles = [aws_iam_role.vmimport.name]
  policy_arn = aws_iam_policy.upload-machine-images.arn
}

# ----- dns zone -----

resource "aws_route53_zone" "private" {
  name = "jm-private.awardwallet.com"
  vpc {
    vpc_id = aws_vpc.main.id
  }
}

resource "aws_route53_zone" "infra" {
  name = "infra.awardwallet.com"
  vpc {
    vpc_id = aws_vpc.main.id
  }
}

# ----- mongo -----

resource "aws_security_group" "mongo" {
  name = "mongo"
  description = "mongo instances"
  vpc_id = aws_vpc.main.id
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
    from_port = 27017
    to_port = 27017
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id, aws_security_group.web.id]
    description = "mongo"
    self = true
  }
}

data "aws_instance" "mongo1a" {
  filter {
    name   = "tag:Name"
    values = ["mongo-1a"]
  }
}

data "aws_instance" "mongo1b" {
  filter {
    name   = "tag:Name"
    values = ["mongo-1b"]
  }
}

data "aws_instance" "mongoarb" {
  filter {
    name   = "tag:Name"
    values = ["mongo-arbiter"]
  }
}

resource "aws_route53_record" "mongo1a" {
  name    = "mongo1a"
  type    = "A"
  zone_id = aws_route53_zone.private.zone_id
  ttl = 60
  records = [data.aws_instance.mongo1a.private_ip]
}

resource "aws_route53_record" "mongo1b" {
  name    = "mongo1b"
  type    = "A"
  zone_id = aws_route53_zone.private.zone_id
  ttl = 60
  records = [data.aws_instance.mongo1b.private_ip]
}

resource "aws_route53_record" "mongoarb" {
  name    = "mongoarb"
  type    = "A"
  zone_id = aws_route53_zone.private.zone_id
  ttl = 60
  records = [data.aws_instance.mongoarb.private_ip]
}

#resource "aws_instance" "mongo-1a" {
#  ami = data.aws_ami.base.id
#  instance_type = "t3a.large"
#  iam_instance_profile = aws_iam_instance_profile.docker_instance.id
#  key_name = aws_key_pair.main.key_name
#  associate_public_ip_address = true
#  subnet_id = aws_subnet.main.id
#  vpc_security_group_ids = [
#    aws_security_group.mongo.id,
#    aws_security_group.ssh-admin.id
#  ]
#  tags = {
#    Name = "mongo-1a"
#  }
#}

resource "aws_cloudwatch_log_group" "main" {
  name = "task"
  retention_in_days = 30
}

############## proxy auth ###################

resource "aws_security_group" "proxy_auth" {
  name = "proxy-auth"
  description = "proxy auth instance"
  vpc_id = aws_vpc.main.id
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
    from_port = 3128
    to_port = 3200
    protocol = "tcp"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
    description = "public proxy access"
  }
  ingress {
    from_port = 11211
    to_port = 11211
    protocol = "tcp"
    security_groups = [aws_security_group.workers.id]
    description = "memcached api for workers for setting proxy auth"
  }
}

data "aws_security_group" "vpn-servers" {
  id = "sg-06fb7c8acb04dbe42"
}

resource "aws_instance" "lpm" {
  ami = data.aws_ami.base.id
  instance_type = "t3a.small"
  iam_instance_profile = aws_iam_instance_profile.docker_instance.name
  key_name = aws_key_pair.main.key_name
  associate_public_ip_address = true
  subnet_id = aws_subnet.main.id
  vpc_security_group_ids = [
    aws_security_group.lpm.id,
    aws_security_group.ssh-admin.id,
    data.aws_security_group.vpn-servers.id
  ]
  tags = {
    Name = "lpm-for-wrapped-proxies"
  }
}

resource "aws_route53_record" "lpm" {
  zone_id = aws_route53_zone.infra.id
  name    = "lpm.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.lpm.private_ip]
}


