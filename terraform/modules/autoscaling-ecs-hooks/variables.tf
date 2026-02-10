variable "name" {
  type = string
}

variable "autoscaling_group_names" {
  type = set(string)
}

variable "ecs_cluster_name" {
  type = string
}