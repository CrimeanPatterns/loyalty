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
        launch_template_id = "lt-0ca26496ed00927aa"
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
