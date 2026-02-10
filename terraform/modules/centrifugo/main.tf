resource "aws_security_group" "centrifugo" {
  name = "${var.cluster_name}-centrifugo"
  description = "centrifugo server for extension check"
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
    from_port = 8000
    to_port = 8000
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "admin api for clients"
  }
}

resource "aws_instance" "centrifugo" {
  ami = var.ami
  instance_type = var.instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = false
  subnet_id = var.subnet_id
  vpc_security_group_ids = [
    aws_security_group.centrifugo.id,
    var.ssh_admin_security_group_id
  ]
  tags = {
    Name = "${var.cluster_name}-centrifugo"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    volume_size = 30
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data, ami]
  }
}

resource "aws_route53_record" "centrifugo" {
  zone_id = var.infra_zone_id
  name    = "centrifugo.${var.cluster_name}.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.centrifugo.private_ip]
}
