data "aws_ami" "nat-server" {
  most_recent = true
  filter {
    name = "name"
    values = ["nat-server-*"]
  }
  owners = ["self"]
}

resource "aws_security_group" "nat-server" {
  name = "nat-server"
  description = "nat servers"
  vpc_id = var.vpc_id
  egress {
    from_port = 0
    to_port = 0
    protocol = "-1"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
    description = "download anything"
  }
  ingress {
    from_port = 0
    to_port = 0
    protocol = "-1"
    cidr_blocks = var.allowed_cidr_blocks
    description = "nat"
  }
}

resource "aws_instance" "nat-server" {
  ami = data.aws_ami.nat-server.id
  instance_type = var.instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = true
  subnet_id = var.subnet_id
  vpc_security_group_ids = [
    aws_security_group.nat-server.id,
    var.ssh_admin_security_group_id
  ]
  source_dest_check = false
  tags = {
    Name = "nat-server"
    map-migrated = "mig47932"
    "proxy_access" = ""
    "services" = ""
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      "map-migrated" = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [root_block_device[0].volume_size] # ami,
  }
}

resource "aws_eip" "nat-server" {
  tags = {
    Name = "nat-server"
    "map-migrated" = "mig47932"
  }
}

resource "aws_eip_association" "nat-server" {
  allocation_id = aws_eip.nat-server.allocation_id
  instance_id = aws_instance.nat-server.id
}

