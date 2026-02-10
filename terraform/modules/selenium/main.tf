resource "aws_security_group" "consul" {
  name = "consul"
  description = "consul instances"
  vpc_id = var.vpc_id
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
    security_groups = concat(var.allowed_security_group_ids, [aws_security_group.selenium.id])
    description = "consul http api"
  }
  ingress {
    from_port = 8500
    to_port = 8500
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "consul http api"
  }
}

data "aws_ami" "consul" {
  most_recent = true
  filter {
    name = "name"
    values = ["consul-*"]
  }
  owners = ["self"]
}

resource "aws_instance" "consul" {
  ami = data.aws_ami.consul.image_id
  instance_type = var.consul_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = false
  subnet_id = var.subnet_id
  disable_api_termination = false
  ebs_optimized = true
  hibernation = false
  monitoring = false
  vpc_security_group_ids = [
    aws_security_group.consul.id,
    var.ssh_admin_security_group_id
  ]
  tags = {
    Name = "consul"
    "map-migrated" = "mig47932"
  }
  tags_all = {
    "map-migrated" = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      "map-migrated" = "mig47932"
    }
  }
}

resource "aws_route53_record" "consul" {
  zone_id = var.infra_zone_id
  name    = "selenium-consul.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.consul.private_ip]
}

resource "aws_security_group" "selenium" {
  name = "selenium"
  description = "selenium instances"
  vpc_id = var.vpc_id
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
    security_groups = var.allowed_security_group_ids
    description = "different browsers on multiple selenium ports"
  }
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

resource "aws_iam_instance_profile" "selenium_instance" {
  name = "selenium-instance"
  role = aws_iam_role.selenium_instance.name
}

resource "aws_iam_policy" "selenium_instance" {
  name = "selenium-instance"
  description = "Selenium instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "getSeleniumImages",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": [
                "arn:aws:ecr:*:718278292471:repository/selenium2",
                "arn:aws:ecr:*:718278292471:repository/fluentbit"
            ]
        },
        {
            "Sid": "CloudWatch",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricStatistics",
                "cloudwatch:PutMetricData"
            ],
            "Resource": "*"
        },
        {
            "Sid": "ECS",
            "Effect": "Allow",
            "Action": [
                "ecr:GetAuthorizationToken",
                "ecs:DeregisterContainerInstance",
                "ecs:DiscoverPollEndpoint",
                "ecs:Poll",
                "ecs:RegisterContainerInstance",
                "ecs:StartTelemetrySession",
                "ecs:Submit*",
                "ecs:UpdateContainerInstancesState"
            ],
            "Resource": "*"
        }
    ]
}
EOF
}

resource "aws_iam_policy_attachment" "selenium-instance-attach-selenium" {
  name = "selenium-selenium-attachment"
  roles = [aws_iam_role.selenium_instance.name]
  policy_arn = aws_iam_policy.selenium_instance.arn
}

data "aws_ami" "selenium" {
  most_recent = true
  filter {
    name = "name"
    values = ["selenium-*"]
  }
  owners = ["self"]
}

module "servers" {
  source = "../ecs-service"
  service_name = "selenium"
  asg_name = "selenium"
  iam_role_name = aws_iam_role.selenium_instance.name
  instance_types = ["m5a.xlarge", "t3a.xlarge", "t3.xlarge", "t2.xlarge", "m3.xlarge", "m4.xlarge", "m5.xlarge"]
  security_group_ids = [aws_security_group.selenium.id, var.ssh_admin_security_group_id] // workers, ssh-admin
  ami_id = data.aws_ami.selenium.image_id
  ecs_cluster_id = var.ecs_cluster_id
  ecs_cluster_name = var.ecs_cluster_name
  subnet_ids = [var.subnet_id]
  vpc_id = var.vpc_id
  balancers = []
  instance_tags = {
    "proxy_access" = ""
  }
  snapshot_id = one(data.aws_ami.selenium.block_device_mappings).ebs.snapshot_id
  min_instances = var.min_instances
  min_healthy_percentage = var.min_healthy_percentage
  desired_capacity = 1
  key-pair-name = var.ssh_keypair_name
  on_demand_base_capacity = 0
  empty_task_definition = var.empty_task_definition
  service_registries = []
  associate_public_ip_address = false
}

resource "aws_autoscaling_policy" "seleniums-scale-up" {
  name                   = "seleniums-scale-up"
  scaling_adjustment     = 10
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 90
  autoscaling_group_name = module.servers.asg_name
}

resource "aws_cloudwatch_metric_alarm" "seleniums-scale-up" {
  alarm_name          = "seleniums-scale-up"
  comparison_operator = "LessThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "healthy-selenium-servers"
  namespace           = "AW/Loyalty"
  period              = "10"
  statistic           = "Minimum"
  threshold           = var.desired_healthy_servers
  alarm_description = "scale seleniums up when there are no free servers"
  alarm_actions     = [aws_autoscaling_policy.seleniums-scale-up.arn]
  tags = {
    "map-migrated" = "mig47932"
  }
}

resource "aws_autoscaling_policy" "seleniums-scale-down" {
  name                   = "seleniums-scale-down"
  scaling_adjustment     = -5
  adjustment_type        = "PercentChangeInCapacity"
  cooldown               = 120
  autoscaling_group_name = module.servers.asg_name
}

resource "aws_cloudwatch_metric_alarm" "seleniums-scale-down" {
  alarm_name          = "seleniums-scale-down"
  comparison_operator = "GreaterThanOrEqualToThreshold"
  evaluation_periods  = "20"
  metric_name         = "healthy-selenium-servers"
  namespace           = "AW/Loyalty"
  period              = "60"
  statistic           = "Minimum"
  threshold           = ceil(var.desired_healthy_servers * 1.5)
  alarm_description = "scale seleniums down when there are too much free servers"
  alarm_actions     = [aws_autoscaling_policy.seleniums-scale-down.arn]
  tags = {
    "map-migrated" = "mig47932"
  }
}

