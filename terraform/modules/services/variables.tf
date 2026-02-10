variable "vpc_id" {
  type = string
}

variable "allowed_security_group_ids" {
  type = list(string) # aws_security_group.workers.id, aws_security_group.selenium.id
}

variable "allowed_cidr_blocks" {
  type = list(string) # "${var.builder_ip}/32", "${var.office_vpn_ip}/32"
}

variable "memcached_ami" {
  type = string
}

variable "memcached_instance_type" {
  type = string # t3a.micro
}

variable "files_ami" {
  type = string
}

variable "files_instance_type" {
  type = string # t3a.small
}

variable "mysql_ami" {
  type = string
}

variable "mysql_instance_type" {
  type = string # t3a.micro
}

variable "rabbitmq_ami" {
  type = string
}

variable "rabbitmq_instance_type" {
  type = string
}

variable "mongo_ami" {
  type = string
}

variable "mongo_node_instance_type" {
  type = string # t3a.small
}

variable "mongo_arbiter_instance_type" {
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

variable "infra_zone_id" {
  type = string
}

variable "mongo_disk_size" {
  type = number
}

variable "prefix" {
  type = string
}

variable "extra_files_security_group_ids" {
  type = list(string)
  default = []
}

variable "extra_memcached_security_group_ids" {
  type = list(string)
  default = []
}

variable "extra_rabbitmq_security_group_ids" {
  type = list(string)
  default = []
}

variable "extra_mysql_security_group_ids" {
  type = list(string)
  default = []
}

variable "extra_mongo_security_group_ids" {
  type = list(string)
  default = []
}

variable "associate_public_ip" {
  type = bool
  default = false
}