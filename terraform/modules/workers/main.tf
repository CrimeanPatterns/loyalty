resource "aws_security_group" "workers" {
  name = var.sg_name == null ? "${var.service_name}-workers" : var.sg_name
  description = "loyalty worker instances"
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
  lifecycle {
    ignore_changes = [description]
  }
}

module "workers" {
  source = "../ecs-service"
  vpc_id = var.vpc_id
  security_group_ids = [aws_security_group.workers.id, var.ssh_admin_security_group_id]
  key-pair-name = var.ssh_keypair_name
  subnet_ids = [var.subnet_id]
  ami_id = var.ecs_based_ami_id
  ecs_cluster_id = var.ecs_cluster_id
  ecs_cluster_name = var.ecs_cluster_name
  snapshot_id = var.snapshot_id
  empty_task_definition = var.empty_task_definition
  asg_name = var.asg_name != null ? var.asg_name : "${var.ecs_cluster_name}-${var.service_name}"
  balancers = []
  iam_role_name = var.iam_role
  instance_types = ["t3a.large", "m5a.large", "m5.large"]
  on_demand_base_capacity = var.on_demand_base_capacity
  service_name = var.service_name
  instance_tags = {"proxy_access": ""}
  service_registries = []
  desired_capacity = var.desired_capacity
  min_healthy_percentage = var.min_healthy_percentage
  min_instances = var.min_instances
  associate_public_ip_address = var.associate_public_ip_address
}

