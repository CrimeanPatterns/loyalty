resource "aws_iam_policy" "lpm-instance" {
  name = "${var.cluster_name}-lpm-instance"
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
                "arn:aws:ecr:*:${var.main_awardwallet_organization_id}:repository/lpm",
                "arn:aws:ecr:*:${var.main_awardwallet_organization_id}:repository/services/lpm"
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

resource "aws_iam_role" "lpm-instance" {
  name = "${var.cluster_name}-lpm-instance"
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

resource "aws_iam_instance_profile" "lpm-instance" {
  name = "${var.cluster_name}-lpm-instance"
  role = aws_iam_role.lpm-instance.name
}

resource "aws_iam_policy_attachment" "lpm-instance-attach" {
  name = "${var.cluster_name}-lpm-attachment"
  roles = [aws_iam_role.lpm-instance.name]
  policy_arn = aws_iam_policy.lpm-instance.arn
}

resource "aws_security_group" "lpm" {
  name = "${var.cluster_name}-lpm"
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
    from_port = 22999
    to_port = 22999
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "admin api for clients"
  }
  dynamic "ingress" {
    for_each = var.allowed_cidr_blocks
    content {
      from_port = 22000
      to_port = 65000
      protocol = "tcp"
      cidr_blocks = [ingress.value]
      description = "webdriver-cluster-2"
    }
  }
  ingress {
    from_port = 22000
    to_port = 65000
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "proxy ports for clients"
  }
}

resource "aws_service_discovery_service" "lpm" {
  name = "lpm"
  dns_config {
    namespace_id = var.service_discovery_private_dns_namespace_id
    dns_records {
      ttl = 10
      type = "SRV"
    }
    routing_policy = "MULTIVALUE"
  }
  health_check_custom_config {
    failure_threshold = 2
  }
}

# will be overwritten with deploy
resource "aws_ecs_task_definition" "scratch" {
  container_definitions = jsonencode([
    {
      name = "lpm"
      image = "nginx"
      cpu = 10
      memory = 256
      essential = true
      portMappings = [
        {
          protocol = "tcp",
          containerPort = 22999,
          hostPort = 22999
        }
      ]
    }
  ])
  family = "${var.cluster_name}-lpm"
}

module "ecs" {
  source = "../ecs-service"
  vpc_id = var.vpc_id
  security_group_ids = [aws_security_group.lpm.id, var.ssh_admin_security_group_id]
  key-pair-name = var.ssh_keypair_name
  subnet_ids = [var.subnet_id]
  ami_id = var.ami
  ecs_cluster_id = var.ecs_cluster_id
  ecs_cluster_name = var.cluster_name
  snapshot_id = var.snapshot_id
  empty_task_definition = aws_ecs_task_definition.scratch.family
  asg_name = "lpm"
  balancers = []
  iam_role_name = aws_iam_role.lpm-instance.name
  instance_types = [var.instance_type]
  on_demand_base_capacity = 1
  min_instances = 0
  min_healthy_percentage = 0
  desired_capacity = 0
  service_name = "lpm"
  instance_tags = {"proxy_access": ""}
  service_registries = [
    {
      registry_arn: aws_service_discovery_service.lpm.arn,
      container_name: "lpm",
      container_port: 22999
    }
  ]
  associate_public_ip_address = false
}

