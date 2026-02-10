variable "instances" {
  type = map(object({id: string, threshold: number}))
}
variable "alarm_action" {
  type = string
}

variable "project_name" {
  type = string
}

variable "metric_name" {
  type = string
  default = "CPUUtilization"
}

variable "compare_operation" {
  type = string
  default = "GreaterThanThreshold"
}