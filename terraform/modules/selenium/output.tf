output "asg_name" {
  value = module.servers.asg_name
}

output "security_group_id" {
  value = aws_security_group.selenium.id
}