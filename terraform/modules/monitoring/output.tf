output "alarm_action" {
  value = module.notify_slack.slack_topic_arn
}