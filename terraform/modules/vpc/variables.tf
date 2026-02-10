variable "cidr_block_start" {
  type = string
  description = "first two digits of ip, like 172.30. we need 16 bit range"
}

variable "peering_connections" {
  type = map(object({
    cidr_block = string # "192.168.0.0/16"
    peer_owner_id = string # var.awardwallet_main_account_id
    peer_vpc_id = string # var.awardwallet_main_vpc_id
  }))
}

variable "ssh_admin_security_group_id" {
  type = string # aws_security_group.ssh-admin.id
}

variable "ssh_keypair_name" {
  type = string # aws_key_pair.main.key_name
}

