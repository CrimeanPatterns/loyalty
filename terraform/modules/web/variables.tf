variable "vpc_id" {
  type = string
}

variable "ssh_keypair_name" {
  type = string # aws_key_pair.main.key_name
}

variable "subnet_id" {
  type = string # aws_subnet.main.id
}

variable "other_subnet_id" {
  type = string # aws_subnet.main.id
}

variable "ssh_admin_security_group_id" {
  type = string # aws_security_group.ssh-admin.id
}

variable "ecs_based_ami_id" {
  type = string
}

variable "snapshot_id" {
  type = string # one(data.aws_ami.ecs_based.block_device_mappings).ebs.snapshot_id
}

variable "ecs_cluster_id" {
  type = string
}

variable "ecs_cluster_name" {
  type = string
}

variable "empty_task_definition" {
  type = string
}

variable "infra_zone_id" {
  type = string
}

variable "iam_role" {
  type = string
}

variable "api_domain_name" {
  type    = string
}

variable "hotels_domain_name" {
  type    = string
}

variable "dns_zone_id" {
  type    = string
}

variable "on_demand_base_capacity" {
  type = number
}

variable "service_name" {
  type = string
  default = "web"
}

variable "lb_subnet_ids" {
  type = list(string)
}