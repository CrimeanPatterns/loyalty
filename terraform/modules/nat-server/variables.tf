variable "ssh_keypair_name" {
  type = string # aws_key_pair.main.key_name
}

variable "subnet_id" {
  type = string # aws_subnet.main.id
}

variable "vpc_id" {
  type = string
}

variable "ssh_admin_security_group_id" {
  type = string # aws_security_group.ssh-admin.id
}

variable "allowed_cidr_blocks" {
  type = list(string) # "${var.builder_ip}/32", "${var.office_vpn_ip}/32"
}

variable "instance_type" {
  type = string
  default = "t4g.micro"
}