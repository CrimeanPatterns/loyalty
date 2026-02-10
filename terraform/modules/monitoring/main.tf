data aws_ssm_parameter slack-url {
  name   = "/config/slack-alarm-url"
  with_decryption = true
}

module "notify_slack" {
  source  = "terraform-aws-modules/notify-slack/aws"
  version = "~> 4.0"

  sns_topic_name = "slack"
  slack_webhook_url = data.aws_ssm_parameter.slack-url.value
  slack_channel     = var.slack_channel
  slack_username    = "CloudWatch"
  lambda_function_name = "loyalty_notify_slack"

  #  recreate_missing_package = false

  # VPC
  #  lambda_function_vpc_subnet_ids = module.vpc.intra_subnets
  #  lambda_function_vpc_security_group_ids = [module.vpc.default_security_group_id]

  tags = {
    Name = "cloudwatch-alerts-to-slack"
  }
}

resource "aws_cloudwatch_metric_alarm" "high-queue" {
  alarm_name          = "high-queue"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "messages_ready"
  namespace           = "AW/Rabbit"
  dimensions = {
    host = var.host
  }
  period              = "60"
  statistic           = "Maximum"
  threshold           = "7000"
  alarm_description = "There are queue, check workers"
  alarm_actions     = [module.notify_slack.slack_topic_arn]
  treat_missing_data = "breaching"
}

resource "aws_cloudwatch_metric_alarm" "free-threads" {
  alarm_name          = "free-threads"
  comparison_operator = "LessThanThreshold"
  metric_name         = "free_threads"
  namespace           = "AW/Loyalty"
  period              = "60"
  statistic           = "Minimum"
  threshold           = tostring(var.min_free_threads)
  alarm_description = "There are no free threads check workers"
  alarm_actions     = [module.notify_slack.slack_topic_arn]
  evaluation_periods  = 3
  datapoints_to_alarm = 3
  treat_missing_data = "breaching"
}

resource "aws_cloudwatch_metric_alarm" "undelivered-callbacks" {
  alarm_name          = "undelivered-callbacks"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = "1"
  metric_name         = "check-account-no-callback"
  namespace           = "AW/Loyalty"
  period              = "900"
  statistic           = "Average"
  threshold           = tostring(var.max_undelivered_callbacks)
  alarm_description = "There are undelivered callbacks, check send callback workers"
  alarm_actions     = [module.notify_slack.slack_topic_arn]
  treat_missing_data = "breaching"
}
