variable "subnet_ids" {
  type = list(string)
}

variable "vpc_id" {
  type = string
}

variable "instance_types" {
  type = list(string)
}

variable "on_demand_base_capacity" {
  type = number
}

variable "min_instances" {
  type = number
  default = 2
}

variable "min_healthy_percentage" {
  type = number
  default = 50
}

variable "desired_capacity" {
    type = number
    default = 2
}

variable "asg_name" {
  type = string
}

variable "associate_public_ip_address" {
  type = bool
}