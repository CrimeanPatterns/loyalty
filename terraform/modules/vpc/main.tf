resource "aws_vpc" "main" {
  tags = {
    Name = "main"
  }
  cidr_block = "${var.cidr_block_start}.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support = true
}

resource "aws_subnet" "main_a" {
  vpc_id = aws_vpc.main.id
  cidr_block = "${var.cidr_block_start}.16.0/20"
  tags = {
    Name = "main-a"
  }
  availability_zone = "us-east-1a"
  map_public_ip_on_launch = true
}

resource "aws_subnet" "main_b" {
  vpc_id = aws_vpc.main.id
  cidr_block = "${var.cidr_block_start}.32.0/20"
  tags = {
    Name = "main-b"
  }
  availability_zone = "us-east-1b"
  map_public_ip_on_launch = true
}

resource "aws_egress_only_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  tags = {
    Name = "main"
  }
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
  tags = {
    Name = "main"
  }
}

resource "aws_default_route_table" "main" {
  default_route_table_id = aws_vpc.main.default_route_table_id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  dynamic "route" {
    for_each = var.peering_connections
    content {
      cidr_block = route.value.cidr_block
      vpc_peering_connection_id = aws_vpc_peering_connection.to[route.key].id
    }
  }
  tags = {
    Name = "main"
  }
}

resource "aws_route_table_association" "mfi_route_table_association" {
  subnet_id = aws_subnet.main_b.id
  route_table_id = aws_route_table.nat.id
}

resource "aws_route_table_association" "mfi_route_table_association_b" {
  subnet_id = aws_subnet.main_a.id
  route_table_id = aws_default_route_table.main.id
}

resource "aws_vpc_peering_connection" "to" {
  for_each = var.peering_connections
  peer_owner_id = each.value.peer_owner_id
  peer_vpc_id   = each.value.peer_vpc_id
  vpc_id        = aws_vpc.main.id
  tags = {
    Name = "to-${each.key}"
  }
}

resource "aws_subnet" "nat-server-b" {
  vpc_id = aws_vpc.main.id
  cidr_block = "${var.cidr_block_start}.128.0/20"
  tags = {
    Name = "nat-server-b"
  }
  availability_zone = "us-east-1b"
  map_public_ip_on_launch = true
}

resource "aws_subnet" "nat-server-a" {
  vpc_id = aws_vpc.main.id
  cidr_block = "${var.cidr_block_start}.64.0/20"
  tags = {
    Name = "nat-server-a"
  }
  availability_zone = "us-east-1a"
  map_public_ip_on_launch = true
}

resource "aws_route_table" "nat-server" {
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  dynamic "route" {
    for_each = var.peering_connections
    content {
      cidr_block = route.value.cidr_block
      vpc_peering_connection_id = aws_vpc_peering_connection.to[route.key].id
    }
  }
  tags = {
    Name = "nat-server"
  }
  vpc_id = aws_vpc.main.id
}

resource "aws_route_table_association" "nat-server-b" {
  route_table_id = aws_route_table.nat-server.id
  subnet_id = aws_subnet.nat-server-b.id
}

resource "aws_route_table_association" "nat-server-a" {
  route_table_id = aws_route_table.nat-server.id
  subnet_id = aws_subnet.nat-server-a.id
}

module "nat-server" {
  source = "../nat-server"
  allowed_cidr_blocks = ["${var.cidr_block_start}.0.0/16"]
  ssh_admin_security_group_id = var.ssh_admin_security_group_id
  ssh_keypair_name = var.ssh_keypair_name
  vpc_id = aws_vpc.main.id
  subnet_id = aws_subnet.nat-server-b.id
}

resource "aws_route_table" "nat" {
  route {
    cidr_block = "0.0.0.0/0"
    instance_id = module.nat-server.instance-id
  }
  route {
    ipv6_cidr_block        = "::/0"
    egress_only_gateway_id = aws_egress_only_internet_gateway.main.id
  }
  dynamic "route" {
    for_each = var.peering_connections
    content {
      cidr_block = route.value.cidr_block
      vpc_peering_connection_id = aws_vpc_peering_connection.to[route.key].id
    }
  }
  tags = {
    Name = "nat"
  }
  vpc_id = aws_vpc.main.id
}

resource "aws_route53_zone" "private" {
  name = "infra.awardwallet.com"
  tags = {
    "map-migrated" = "mig47932"
  }
  tags_all = {
    "map-migrated" = "mig47932"
  }
  vpc {
    vpc_id = aws_vpc.main.id
  }
}