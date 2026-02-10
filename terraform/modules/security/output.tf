output "ssh_admin_security_group_id" {
  value = aws_security_group.ssh-admin.id
}

output "ssh_keypair_name" {
  value = aws_key_pair.main.key_name
}

output "loyalty_instance_iam_role_name" {
  value = aws_iam_role.loyalty_instance.name
}