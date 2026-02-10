data "aws_caller_identity" "current" {}

locals {
  main_awardwallet_organization_id = "718278292471"
}

resource "aws_key_pair" "main" {
  key_name = "main"
  public_key = var.ssh_public_key
}

resource "aws_security_group" "ssh-admin" {
  name = "ssh-admin"
  description = "ssh access from jumphost admingate and builder"
  vpc_id = var.vpc_id
  ingress {
    from_port = 22
    to_port = 22
    protocol = "tcp"
    cidr_blocks = var.ssh_admin_cidr_blocks
    description = "ssh from builder and admingate"
  }
  ingress {
    protocol = "icmp"
    cidr_blocks = var.ssh_admin_cidr_blocks
    from_port = -1
    to_port = -1
    description = "ping from builder and admingate"
  }
}

resource "aws_iam_policy" "loyalty_instance" {
  name = "loyalty-instance"
  description = "web or worker instances"
  policy = <<EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "S3",
            "Effect": "Allow",
            "Action": [
              "s3:GetObject",
              "s3:ListBucket",
              "s3:PutObject",
              "s3:PutObjectAcl"
            ],
            "Resource": [
                "arn:aws:s3:::${var.s3_log_bucket}/*",
                "arn:aws:s3:::${var.s3_log_bucket}"
            ]
        },
        {
            "Sid": "SSM",
            "Effect": "Allow",
            "Action": [
                "ssm:GetParametersByPath",
                "ssm:GetParameters",
                "ssm:GetParameter"
            ],
            "Resource": [
                "arn:aws:ssm:*:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_path}/*",
                "arn:aws:ssm:*:${data.aws_caller_identity.current.account_id}:parameter${var.ssm_path}"
            ]
        },
        {
            "Sid": "CloudWatch",
            "Effect": "Allow",
            "Action": [
                "cloudwatch:DescribeAlarms",
                "cloudwatch:GetMetricStatistics",
                "cloudwatch:PutMetricData",
                "logs:CreateLogStream",
                "logs:CreateLogGroup",
                "logs:DescribeLogGroups",
                "logs:DescribeLogStreams",
                "logs:PutLogEvents"
            ],
            "Resource": "*"
        },
        {
            "Sid": "ECR",
            "Effect": "Allow",
            "Action": [
                "ecr:GetDownloadUrlForLayer",
                "ecr:BatchGetImage",
                "ecr:BatchCheckLayerAvailability"
            ],
            "Resource": [
                "arn:aws:ecr:*:${local.main_awardwallet_organization_id}:repository/loyalty",
                "arn:aws:ecr:*:${local.main_awardwallet_organization_id}:repository/engine-sync",
                "arn:aws:ecr:*:${local.main_awardwallet_organization_id}:repository/postfix",
                "arn:aws:ecr:*:${local.main_awardwallet_organization_id}:repository/amqproxy",
                "arn:aws:ecr:*:${local.main_awardwallet_organization_id}:repository/squid"
            ]
        },
        {
            "Sid": "ECS",
            "Effect": "Allow",
            "Action": [
                "ecr:GetAuthorizationToken",
                "ecs:DeregisterContainerInstance",
                "ecs:DiscoverPollEndpoint",
                "ecs:Poll",
                "ecs:RegisterContainerInstance",
                "ecs:StartTelemetrySession",
                "ecs:Submit*",
                "ecs:UpdateContainerInstancesState"
            ],
            "Resource": "*"
        }
    ]
}
EOF
}

resource "aws_iam_role" "loyalty_instance" {
  name = "loyalty-instance"
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

resource "aws_iam_instance_profile" "loyalty_instance" {
  name = "loyalty-instance"
  role = aws_iam_role.loyalty_instance.name
}

resource "aws_iam_policy_attachment" "loyalty-instance-attach-loyalty" {
  name = "loyalty-loyalty-attachment"
  roles = [aws_iam_role.loyalty_instance.name]
  policy_arn = aws_iam_policy.loyalty_instance.arn
}

resource "aws_s3_bucket" "logs" {
  bucket = var.s3_log_bucket
  tags = {
    "map-migrated" = "mig47932"
  }
  tags_all = {
    "map-migrated" = "mig47932"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "aws:kms"
    }
  }
}

resource "aws_s3_bucket_acl" "logs" {
  bucket = aws_s3_bucket.logs.id
  acl    = "private"
}

resource "aws_s3_bucket_lifecycle_configuration" "logs" {
  bucket = aws_s3_bucket.logs.id
  rule {
    id = "expiration"
    status = "Enabled"
    expiration {
      days = 14
    }
  }
}

resource "aws_s3_bucket_public_access_block" "logs" {
  bucket = aws_s3_bucket.logs.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket" "cloudtrail" {
  bucket = var.s3_cloudtrail_bucket
  tags = {
    "map-migrated" = "mig47932"
  }
  tags_all = {
    "map-migrated" = "mig47932"
  }
}

resource "aws_s3_bucket_policy" "cloudtrail" {
  bucket = aws_s3_bucket.cloudtrail.id
  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
      {
          "Sid": "AWSCloudTrailAclCheck",
          "Effect": "Allow",
          "Principal": {"Service": "cloudtrail.amazonaws.com"},
          "Action": "s3:GetBucketAcl",
          "Resource": "arn:aws:s3:::${aws_s3_bucket.cloudtrail.id}"
      },
      {
          "Sid": "AWSCloudTrailWrite",
          "Effect": "Allow",
          "Principal": {"Service": "cloudtrail.amazonaws.com"},
          "Action": "s3:PutObject",
          "Resource": "arn:aws:s3:::${aws_s3_bucket.cloudtrail.id}/AWSLogs/${data.aws_caller_identity.current.account_id}/*",
          "Condition": {"StringEquals": {"s3:x-amz-acl": "bucket-owner-full-control"}}
      }
  ]
}
EOF
}

resource "aws_s3_bucket_server_side_encryption_configuration" "cloudtrail" {
  bucket = aws_s3_bucket.cloudtrail.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "aws:kms"
    }
  }
}

resource "aws_s3_bucket_acl" "cloudtrail" {
  bucket = aws_s3_bucket.cloudtrail.id
  acl    = "private"
}

resource "aws_s3_bucket_lifecycle_configuration" "cloudtrail" {
  bucket = aws_s3_bucket.cloudtrail.id
  rule {
    id = "expiration"
    status = "Enabled"
    expiration {
      days = 90
    }
  }
}

resource "aws_s3_bucket_public_access_block" "cloudtrail" {
  bucket = aws_s3_bucket.cloudtrail.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_cloudtrail" "main" {
  name                          = "main"
  s3_bucket_name                = aws_s3_bucket.cloudtrail.id

  event_selector {
    read_write_type           = "All"
    include_management_events = true

    data_resource {
      type = "AWS::S3::Object"
      values = ["${aws_s3_bucket.logs.arn}/"]
    }
  }
}