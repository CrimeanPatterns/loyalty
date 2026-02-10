variable "vpc_id" {
  type = string
}

variable "allowed_security_group_ids" {
  type = list(string)
}

variable "instance_type" {
  type = string # t4g.small
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

variable "infra_zone_id" {
  type = string
}

