data "aws_caller_identity" "current" {}

resource "aws_cloudwatch_event_connection" "jenkins" {
  name               = "jenkins"
  authorization_type = "BASIC"
  auth_parameters {
    basic {
      username = "jenkins"
      password = var.jenkins_api_key
    }
  }
}

resource "aws_cloudwatch_event_api_destination" "jenkins_update_proxy_white_list" {
  name                             = "jenkins-update-proxy-white-list"
  description                      = "update proxy white list job"
  invocation_endpoint              = "https://jenkins.awardwallet.com/job/Loyalty/job/update-proxy-white-list/build"
  http_method                      = "POST"
  invocation_rate_limit_per_second = 5
  connection_arn                   = aws_cloudwatch_event_connection.jenkins.arn
}

resource "aws_cloudwatch_event_rule" "asg_lifecycle_action" {
  name        = "asg-lifecycle-action"
  description = "used to fire lambda after asg scale event"

  event_pattern = <<EOF
{
  "source": [ "aws.autoscaling" ],
  "detail-type": [ "EC2 Instance-launch Lifecycle Action" ]
}
EOF
}

resource "aws_cloudwatch_event_target" "asg_lifecycle_action" {
  arn  = aws_cloudwatch_event_api_destination.jenkins_update_proxy_white_list.arn
  rule = aws_cloudwatch_event_rule.asg_lifecycle_action.id
  role_arn = "arn:aws:iam::${data.aws_caller_identity.current.account_id}:role/eventbridge-invoke-api-destinations"
}

