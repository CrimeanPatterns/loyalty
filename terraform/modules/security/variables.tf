variable "vpc_id" {
  type = string
}

variable "ssh_admin_cidr_blocks" {
  type = list(string)
}

variable "ssh_public_key" {
  type = string
  description = "data.aws_ssm_parameter.ssh-public-key.value"
}

variable "s3_log_bucket" {
  type = string
}

variable "s3_cloudtrail_bucket" {
  type = string
}

variable "ssm_path" {
  type = string
}