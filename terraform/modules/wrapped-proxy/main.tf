resource "aws_iam_policy" "wrapped-proxy-instance" {
  name = "wrapped-proxy-instance"
  description = "wrapped proxy instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "CloudWatch",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricStatistics",
                "cloudwatch:PutMetricData",
                "logs:CreateLogStream",
                "logs:CreateLogGroup",
                "logs:DescribeLogGroups",
                "logs:DescribeLogStreams",
                "logs:PutLogEvents"
            ],
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
                "arn:aws:ecr:*:${var.main_awardwallet_organization_id}:repository/wrapped-proxy"
            ]
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

resource "aws_iam_role" "wrapped-proxy-instance" {
  name = "wrapped-proxy-instance"
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

resource "aws_iam_instance_profile" "wrapped-proxy-instance" {
  name = "wrapped-proxy-instance"
  role = aws_iam_role.wrapped-proxy-instance.name
}

resource "aws_iam_policy_attachment" "wrapped-proxy-instance-attach" {
  name = "wrapped-proxy-attachment"
  roles = [aws_iam_role.wrapped-proxy-instance.name]
  policy_arn = aws_iam_policy.wrapped-proxy-instance.arn
}

resource "aws_security_group" "wrapped-proxy" {
  name = "wrapped-proxy"
  description = "proxy servers to hide external proxy authorization"
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
    from_port = 3000
    to_port = 3000
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "admin api for clients"
  }
  ingress {
    from_port = 3128
    to_port = 65000
    protocol = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "allow external proxy access"
  }
}

resource "aws_instance" "wrapped-proxy" {
  ami = var.ami
  instance_type = var.instance_type
  iam_instance_profile = aws_iam_instance_profile.wrapped-proxy-instance.name
  key_name = var.ssh_keypair_name
  associate_public_ip_address = true
  subnet_id = var.subnet_id
  vpc_security_group_ids = [
    aws_security_group.wrapped-proxy.id,
    var.ssh_admin_security_group_id
  ]
  tags = {
    Name = "wrapped-proxy"
    map-migrated = "mig47932"
    proxy_access = "yes" # added "yes" because terraform skips creation when empty tags
    ipv4 = "elastic"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      map-migrated = "mig47932"
    }
  }
  user_data = <<EOF
#!/bin/sh
echo "ECS_CLUSTER=${var.cluster_name}" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"wrapped-proxy\"}" >> /etc/ecs/ecs.config
EOF
  lifecycle {
    ignore_changes = [ami]
  }
}

data "aws_eip" "wrapped-proxy" {
  public_ip = var.eip
}

resource "aws_eip_association" "wrapped-proxy" {
  allocation_id = data.aws_eip.wrapped-proxy.id
  instance_id = aws_instance.wrapped-proxy.id
}

resource "aws_route53_record" "wrapped-proxy-int" {
  zone_id = var.infra_zone_id
  name    = "wrapped-proxy.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.wrapped-proxy.private_ip]
}

resource "aws_route53_record" "wrapped-proxy-ext" {
  zone_id = var.public_zone_id
  name    = "wrapped-proxy.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [var.eip]
}

resource "aws_ecs_service" "main" {
  name = "wrapped-proxy"
  cluster = var.ecs_cluster_id
  scheduling_strategy = "DAEMON"
  task_definition = var.empty_task_definition
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == wrapped-proxy"
  }
  deployment_minimum_healthy_percent = 0
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition]
  }
}


