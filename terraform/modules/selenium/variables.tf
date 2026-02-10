variable "vpc_id" {
  type = string
}

variable "allowed_security_group_ids" {
  type = list(string) # aws_security_group.workers.id, aws_security_group.selenium.id
}

variable "allowed_cidr_blocks" {
  type = list(string) # "${var.builder_ip}/32", "${var.office_vpn_ip}/32"
}

variable "consul_instance_type" {
  type = string # t3a.micro
}

variable "ssh_keypair_name" {
  type = string # aws_key_pair.main.key_name
}

variable "subnet_id" {
  type = string # aws_subnet.main.id
}

variable "ssh_admin_security_group_id" {
  type = string # aws_security_group.ssh-admin.id
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

variable "min_instances" {
  type = number
}

variable "min_healthy_percentage" {
  type = number
}

variable "desired_healthy_servers" {
  type = number
}