resource "aws_route53_record" "api-endpoint" {
  zone_id = var.dns_zone_id
  name    = var.api_domain_name
  type    = "A"
  alias {
    name                   = aws_lb.web.dns_name
    zone_id                = aws_lb.web.zone_id
    evaluate_target_health = true
  }
  provider = aws.dns
}

resource "aws_acm_certificate" "api" {
  domain_name       = var.api_domain_name
  validation_method = "DNS"
  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_route53_record" "cert-validation" {
  allow_overwrite = true
  name =  tolist(aws_acm_certificate.api.domain_validation_options)[0].resource_record_name
  records = [tolist(aws_acm_certificate.api.domain_validation_options)[0].resource_record_value]
  type = tolist(aws_acm_certificate.api.domain_validation_options)[0].resource_record_type
  zone_id = var.dns_zone_id
  ttl = 60
  provider = aws.dns
}

resource "aws_acm_certificate_validation" "api" {
  certificate_arn = aws_acm_certificate.api.arn
  validation_record_fqdns = [aws_route53_record.cert-validation.fqdn]
}

resource "aws_route53_record" "hotels-api-endpoint" {
  zone_id = var.dns_zone_id
  name    = var.hotels_domain_name
  type    = "A"
  alias {
    name                   = aws_lb.web.dns_name
    zone_id                = aws_lb.web.zone_id
    evaluate_target_health = true
  }
  provider = aws.dns
}

resource "aws_acm_certificate" "hotels" {
  domain_name       = var.hotels_domain_name
  validation_method = "DNS"
  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_route53_record" "hotels-cert-validation" {
  allow_overwrite = true
  name =  tolist(aws_acm_certificate.hotels.domain_validation_options)[0].resource_record_name
  records = [tolist(aws_acm_certificate.hotels.domain_validation_options)[0].resource_record_value]
  type = tolist(aws_acm_certificate.hotels.domain_validation_options)[0].resource_record_type
  zone_id = var.dns_zone_id
  ttl = 60
  provider = aws.dns
}

resource "aws_acm_certificate_validation" "hotels" {
  certificate_arn = aws_acm_certificate.hotels.arn
  validation_record_fqdns = [aws_route53_record.hotels-cert-validation.fqdn]
}

resource "aws_security_group" "web_load_balancers" {
  name = "web-load-balancers"
  description = "alb web load balancers"
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
    from_port = 443
    to_port = 443
    protocol = "tcp"
    cidr_blocks = [
      "0.0.0.0/0"
    ]
    ipv6_cidr_blocks = [
      "::/0"
    ]
    description = "public https"
  }
}

resource "aws_lb" "web" {
  name               = "web"
  security_groups    = [aws_security_group.web_load_balancers.id]
  subnets            = var.lb_subnet_ids
  tags = {
    "map-migrated" = "mig47932"
  }
  tags_all = {
    "map-migrated" = "mig47932"
  }
}

resource "aws_lb_target_group" "web" {
  name     = "web"
  port     = 80
  protocol = "HTTP"
  vpc_id   = var.vpc_id
  health_check {
    healthy_threshold = 2
    unhealthy_threshold = 2
    timeout = 20
    interval = 30
    path = "/elb-check"
  }
  deregistration_delay = 30
}

resource "aws_lb_listener" "web" {
  load_balancer_arn = aws_lb.web.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-2017-01"
  certificate_arn   = aws_acm_certificate.api.arn

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.web.arn
  }
}

resource "aws_lb_listener_certificate" "hotels" {
  certificate_arn = aws_acm_certificate.hotels.arn
  listener_arn    = aws_lb_listener.web.arn
}

resource "aws_security_group" "web" {
  name = "web"
  description = "web instances"
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
    to_port = 65535
    protocol = "tcp"
    security_groups = [aws_security_group.web_load_balancers.id]
    description = "load balancers"
  }
}

module "web" {
  source = "../ecs-service"
  vpc_id = var.vpc_id
  security_group_ids = [aws_security_group.web.id, var.ssh_admin_security_group_id]
  key-pair-name = var.ssh_keypair_name
  subnet_ids = [var.subnet_id]
  ami_id = var.ecs_based_ami_id
  ecs_cluster_id = var.ecs_cluster_id
  ecs_cluster_name = var.ecs_cluster_name
  snapshot_id = var.snapshot_id
  empty_task_definition = var.empty_task_definition
  asg_name = var.service_name
  balancers = [
    {
      container_name = "nginx"
      container_port = 80
      target_group_arn = aws_lb_target_group.web.arn
    }
  ]
  iam_role_name = var.iam_role
  instance_types = ["t3a.small"]
  on_demand_base_capacity = var.on_demand_base_capacity
  service_name = var.service_name
  service_registries = []
  associate_public_ip_address = false
}

