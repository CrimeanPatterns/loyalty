variable "vpc_id" {
  type = string
}

variable "allowed_security_group_ids" {
  type = list(string) # aws_security_group.workers.id, aws_security_group.selenium.id
}

variable "instance_type" {
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

variable "cluster_name" {
  type = string
}

variable "ami" {
  type = string
}

variable "main_awardwallet_organization_id" {
  type = string
}

variable "ecs_cluster_id" {
  type = string
}

variable "empty_task_definition" {
  type = string
}

variable "snapshot_id" {
  type = string
}

variable "service_discovery_private_dns_namespace_id" {
  type = string
}

variable "allowed_cidr_blocks" {
  type = list(string)
}