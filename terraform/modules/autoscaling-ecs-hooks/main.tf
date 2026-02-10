# https://medium.com/@dchouhan93/automating-container-instances-draining-in-amazon-ecs-using-lambda-and-asg-lifecycle-hook-383c8f3557f7
# more details at:
# https://aws.amazon.com/blogs/compute/how-to-automate-container-instance-draining-in-amazon-ecs/
# https://github.com/aws-samples/ecs-cid-sample/blob/master/cform/ecs.yaml

data "aws_caller_identity" "current" {}

resource "aws_iam_role" "sns-lambda" {
  name = "sns-lambda-${var.name}"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "autoscaling.amazonaws.com"
      },
      "Effect": "Allow"
    }
  ]
}
EOF
  managed_policy_arns = ["arn:aws:iam::aws:policy/service-role/AutoScalingNotificationAccessRole", aws_iam_policy.sns-lambda.arn]
}

resource "aws_iam_policy" "sns-lambda" {
  name = "sns-lambda-${var.name}"
  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
      {
          "Effect": "Allow",
          "Resource": "*",
          "Action": [
              "sqs:SendMessage",
              "sqs:GetQueueUrl",
              "sns:Publish"
          ]
      }
  ]
}
EOF
}

resource "aws_iam_role" "sns-lambda-execution" {
  name = "sns-lambda-execution-${var.name}"
  assume_role_policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Action": "sts:AssumeRole",
      "Principal": {
        "Service": "lambda.amazonaws.com"
      },
      "Effect": "Allow"
    }
  ]
}
EOF
}

resource "aws_iam_policy" "sns-lambda-execution" {
  name = "sns-lambda-execution-${var.name}"
  policy = <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
      {
          "Sid": "VisualEditor0",
          "Effect": "Allow",
          "Action": [
              "logs:CreateLogStream",
              "autoscaling:CompleteLifecycleAction",
              "ecs:UpdateContainerInstancesState",
              "ecs:ListContainerInstances",
              "ecs:DescribeContainerInstances",
              "logs:CreateLogGroup",
              "logs:PutLogEvents",
              "sns:Publish"
          ],
          "Resource": "*"
      }
  ]
}
EOF
}

resource "aws_iam_policy_attachment" "sns-lambda-execution" {
  name = "sns-lambda-execution-${var.name}"
  policy_arn = aws_iam_policy.sns-lambda-execution.arn
  roles = [aws_iam_role.sns-lambda-execution.name]
}

resource "aws_sns_topic" "asg-scaled-in" {
  name = "asg-scaled-in-${var.name}"
}

data "archive_file" "asg-scaled-in-lambda" {
  type        = "zip"
  source_file = "${path.module}/lambda.py"
  output_path = "${path.module}/lambda.py.zip"
}

resource "aws_lambda_function" "asg-scaled-in" {
  filename      = "${path.module}/${data.archive_file.asg-scaled-in-lambda.output_path}"
  function_name = "asg_scaled_in_${var.name}"
  role          = aws_iam_role.sns-lambda-execution.arn
  handler       = "lambda.lambda_handler"
  source_code_hash = filebase64sha256("${path.module}/${data.archive_file.asg-scaled-in-lambda.output_path}")
  runtime = "python3.9"
  memory_size = 128
  timeout = 120
}

resource "aws_lambda_permission" "asg-scale-in" {
  function_name = aws_lambda_function.asg-scaled-in.function_name
  action = "lambda:InvokeFunction"
  principal = "sns.amazonaws.com"
}

resource "aws_sns_topic_subscription" "asg-scaled-in" {
  topic_arn = aws_sns_topic.asg-scaled-in.arn
  protocol  = "lambda"
  endpoint  = aws_lambda_function.asg-scaled-in.arn
}

resource "aws_autoscaling_lifecycle_hook" "instance-removed" {
  name                   = "instance-removed-${var.name}"
  for_each = var.autoscaling_group_names
  autoscaling_group_name = each.value
  default_result         = "CONTINUE"
  heartbeat_timeout      = 60
  lifecycle_transition   = "autoscaling:EC2_INSTANCE_TERMINATING"
  notification_target_arn = aws_sns_topic.asg-scaled-in.arn
  notification_metadata = var.ecs_cluster_name
  role_arn = aws_iam_role.sns-lambda.arn
}

