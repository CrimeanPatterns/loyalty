resource "aws_security_group" "memcached" {
  name = "${var.prefix}memcached"
  description = "memcached instances"
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
    from_port = 11211
    to_port = 11211
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "memcached api for workers"
  }
  ingress {
    from_port = 11211
    to_port = 11211
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "memcached api for builder, to update dop proxy list"
  }
}

resource "aws_instance" "memcached" {
  ami = var.memcached_ami
  instance_type = var.memcached_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.memcached.id,
    var.ssh_admin_security_group_id
  ], var.extra_memcached_security_group_ids)
  tags = {
    Name = "${var.prefix}memcached"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data] # ami,
  }
}

resource "aws_route53_record" "memcached" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}memcached.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.memcached.private_ip]
}

resource "aws_security_group" "mysql" {
  name = "${var.prefix}mysql"
  description = "mysql instances"
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
    from_port = 3306
    to_port = 3306
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "mysql api for workers"
  }
  ingress {
    from_port = 3306
    to_port = 3306
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "mysql api for builder, to update dop proxy list"
  }
}

resource "aws_instance" "mysql" {
  ami = var.mysql_ami
  instance_type = var.mysql_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.mysql.id,
    var.ssh_admin_security_group_id
  ], var.extra_mysql_security_group_ids)
  tags = {
    Name = "${var.prefix}mysql"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data] # ami,
  }
}

resource "aws_route53_record" "mysql" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}mysql.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.mysql.private_ip]
}

resource "aws_security_group" "mongo" {
  name = "${var.prefix}mongo"
  description = "mongo instances"
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
    from_port = 27017
    to_port = 27017
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "mongo api for workers"
    self = true
  }
}

resource "aws_instance" "mongo-1" {
  ami = var.mongo_ami
  instance_type = var.mongo_node_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.mongo.id,
    var.ssh_admin_security_group_id
  ], var.extra_mongo_security_group_ids)
  tags = {
    Name = "${var.prefix}mongo-1"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    volume_size = var.mongo_disk_size
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data, subnet_id] # ami,
  }
}

resource "aws_route53_record" "mongo-1" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}mongo-1.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.mongo-1.private_ip]
}

resource "aws_instance" "mongo-2" {
  ami = var.mongo_ami
  instance_type = var.mongo_node_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.mongo.id,
    var.ssh_admin_security_group_id
  ], var.extra_mongo_security_group_ids)
  tags = {
    Name = "${var.prefix}mongo-2"
    map-migrated = "mig47932"
    backup = ""
    backupCount = "1"
  }
  root_block_device {
    volume_type = "gp3"
    volume_size = var.mongo_disk_size
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data] # ami,
  }
}

resource "aws_route53_record" "mongo-2" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}mongo-2.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.mongo-2.private_ip]
}

resource "aws_instance" "mongo-arbiter" {
  ami = var.mongo_ami
  instance_type = var.mongo_arbiter_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.mongo.id,
    var.ssh_admin_security_group_id
  ], var.extra_mongo_security_group_ids)
  tags = {
    Name = "${var.prefix}mongo-arbiter"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data] # ami,
  }
}

resource "aws_route53_record" "mongo-arbiter" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}mongo-arbiter.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.mongo-arbiter.private_ip]
}

resource "aws_security_group" "rabbitmq" {
  name = "${var.prefix}rabbitmq"
  description = "rabbitmq instances"
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
    from_port = 5672
    to_port = 5672
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "rabbitmq api for workers"
  }
  ingress {
    from_port = 5672
    to_port = 5672
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "rabbitmq api for builder, to trigger engine updater"
  }
}

resource "aws_iam_policy" "cloudwatch-writer" {
  name = "${var.prefix}cloudwatch-writer"
  description = "rabbitmq and other instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "CloudWatch",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:PutMetricData"
            ],
            "Resource": "*"
        }
    ]
}
EOF
}

resource "aws_iam_role" "cloudwatch-writer" {
  name = "${var.prefix}cloudwatch-writer"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "ec2.amazonaws.com"
      },
      "Effect": "Allow",
      "Sid": ""
    }
  ]
}
EOF
}

resource "aws_iam_instance_profile" "cloudwatch-writer" {
  name = "${var.prefix}cloudwatch-writer"
  role = aws_iam_role.cloudwatch-writer.name
}

resource "aws_iam_policy_attachment" "cloudwatch-writer" {
  name = "${var.prefix}cloudwatch-writer"
  roles = [aws_iam_role.cloudwatch-writer.name]
  policy_arn = aws_iam_policy.cloudwatch-writer.arn
}

resource "aws_instance" "rabbitmq" {
  ami = var.rabbitmq_ami
  instance_type = var.rabbitmq_instance_type
  iam_instance_profile = aws_iam_instance_profile.cloudwatch-writer.name
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat([
    aws_security_group.rabbitmq.id,
    var.ssh_admin_security_group_id
  ], var.extra_rabbitmq_security_group_ids)
  tags = {
    Name = "${var.prefix}rabbitmq"
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
    ignore_changes = [user_data, root_block_device[0].volume_size] # ami,
  }
}

resource "aws_route53_record" "rabbitmq" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}rabbitmq.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.rabbitmq.private_ip]
}

resource "aws_security_group" "files" {
  name = "${var.prefix}files"
  description = "engine files servers"
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
    from_port = 111
    to_port = 111
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "nfs api for workers"
  }
  ingress {
    from_port = 111
    to_port = 111
    protocol = "udp"
    security_groups = var.allowed_security_group_ids
    description = "nfs api for workers"
  }
  ingress {
    from_port = 2049
    to_port = 2049
    protocol = "tcp"
    security_groups = var.allowed_security_group_ids
    description = "nfs api for workers"
  }
  ingress {
    from_port = 2049
    to_port = 2049
    protocol = "udp"
    security_groups = var.allowed_security_group_ids
    description = "nfs api for workers"
  }
  ingress {
    from_port = 111
    to_port = 111
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "sync engine files from builder"
  }
  ingress {
    from_port = 111
    to_port = 111
    protocol = "udp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "sync engine files from builder"
  }
  ingress {
    from_port = 2049
    to_port = 2049
    protocol = "tcp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "sync engine files from builder"
  }
  ingress {
    from_port = 2049
    to_port = 2049
    protocol = "udp"
    cidr_blocks = var.allowed_cidr_blocks
    description = "sync engine files from builder"
  }
}

resource "aws_instance" "files" {
  ami = var.files_ami
  instance_type = var.files_instance_type
  key_name = var.ssh_keypair_name
  associate_public_ip_address = var.associate_public_ip
  subnet_id = var.subnet_id
  vpc_security_group_ids = concat(var.extra_files_security_group_ids, [
    aws_security_group.files.id,
    var.ssh_admin_security_group_id
  ])
  tags = {
    Name = "${var.prefix}files"
    map-migrated = "mig47932"
  }
  root_block_device {
    volume_type = "gp3"
    tags = {
      map-migrated = "mig47932"
    }
  }
  lifecycle {
    ignore_changes = [user_data, key_name] # ami,
  }
}

resource "aws_route53_record" "files" {
  zone_id = var.infra_zone_id
  name    = "${var.prefix}files.infra.awardwallet.com"
  type    = "A"
  ttl     = "60"
  records = [aws_instance.files.private_ip]
}
