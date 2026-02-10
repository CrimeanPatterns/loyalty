output "vpc_id" {
  value = aws_vpc.main.id
}

output "subnet_id" {
  value = aws_subnet.main_b.id
}

output "other_subnet_id" {
  value = aws_subnet.main_a.id
}

output "infra_zone_id" {
  value = aws_route53_zone.private.id
}

output "nat-server-subnet-b" {
  value = aws_subnet.nat-server-b.id
}

output "nat-server-subnet-a" {
  value = aws_subnet.nat-server-a.id
}