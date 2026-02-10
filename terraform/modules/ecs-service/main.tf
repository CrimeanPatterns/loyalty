data "aws_caller_identity" "current" {}

resource "aws_launch_template" "main" {
  name = "${var.ecs_cluster_name}-${var.service_name}"
  update_default_version = true
  image_id = var.ami_id
  key_name = var.key-pair-name
  tag_specifications {
    resource_type = "instance"
    tags = merge({Name = "${var.ecs_cluster_name}-${var.service_name}", "map-migrated": "mig47932"}, var.instance_tags)
  }
  tag_specifications {
    resource_type = "volume"
    tags = merge({Name = "${var.ecs_cluster_name}-${var.service_name}", "map-migrated": "mig47932"}, var.instance_tags)
  }
  iam_instance_profile {
    name = var.iam_role_name
  }
  ebs_optimized = true
  metadata_options {
    http_endpoint = "enabled"
    http_tokens = "optional"
    http_put_response_hop_limit = 2
    instance_metadata_tags      = "enabled"
  }
  network_interfaces {
    associate_public_ip_address = var.associate_public_ip_address
    security_groups = var.security_group_ids
  }
  block_device_mappings {
    device_name = "/dev/xvda"
    ebs {
      delete_on_termination = true
      snapshot_id = var.snapshot_id
      volume_size = 30
      volume_type = "gp3"
      throughput = 125
    }
  }
  user_data = base64encode(<<EOF
#!/bin/sh
echo "ECS_CLUSTER=${var.ecs_cluster_name}" >>/etc/ecs/ecs.config
echo "ECS_INSTANCE_ATTRIBUTES={\"service\":\"${var.service_name}\"}" >>/etc/ecs/ecs.config
echo "ECS_CONTAINER_STOP_TIMEOUT=6m" >>/etc/ecs/ecs.config
EOF
  )
}

resource "aws_autoscaling_group" "main" {
  name = var.asg_name
  desired_capacity = var.desired_capacity
  max_size = 20
  min_size = var.min_instances
  capacity_rebalance = false
  vpc_zone_identifier = var.subnet_ids
  mixed_instances_policy {
    launch_template {
      launch_template_specification {
        launch_template_id = aws_launch_template.main.id
      }
      dynamic "override" {
        for_each = var.instance_types
        content {
          instance_type = override.value
          weighted_capacity = "1"
        }
      }
    }
    instances_distribution {
      on_demand_base_capacity = var.on_demand_base_capacity
    }
  }
  instance_refresh {
    strategy = "Rolling"
    preferences {
      min_healthy_percentage = var.min_healthy_percentage
    }
  }
  enabled_metrics = [
    "GroupDesiredCapacity",
    "GroupInServiceInstances"
  ]
  lifecycle {
    ignore_changes = [desired_capacity]
  }
}

resource "aws_ecs_service" "main" {
  name = var.service_name
  cluster = var.ecs_cluster_id
  scheduling_strategy = "DAEMON"
  task_definition = var.empty_task_definition
  placement_constraints {
    type = "distinctInstance"
  }
  placement_constraints {
    type = "memberOf"
    expression = "attribute:service == ${var.service_name}"
  }
  dynamic "load_balancer" {
    for_each = var.balancers
    content {
      container_name = load_balancer.value["container_name"]
      container_port = load_balancer.value["container_port"]
      target_group_arn = load_balancer.value["target_group_arn"]
    }
  }
  dynamic "service_registries" {
    for_each = var.service_registries
    content {
      registry_arn = service_registries.value["registry_arn"]
      container_name = service_registries.value["container_name"]
      container_port = service_registries.value["container_port"]
    }
  }
  deployment_minimum_healthy_percent = var.min_healthy_percentage
  deployment_maximum_percent = 100
  lifecycle {
    ignore_changes = [task_definition]
  }
}

resource "aws_autoscaling_lifecycle_hook" "instance_added" {
  name                   = "instance_added"
  autoscaling_group_name = aws_autoscaling_group.main.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 30
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_LAUNCHING"
}

resource "aws_autoscaling_lifecycle_hook" "instance_terminated" {
  name                   = "instance_terminated"
  autoscaling_group_name = aws_autoscaling_group.main.name
  default_result         = "CONTINUE"
  heartbeat_timeout      = 30
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_TERMINATING"
}

