# will be overwritten with deploy
resource "aws_ecs_task_definition" "scratch" {
  container_definitions = jsonencode([
    {
      name = "nginx"
      image = "nginx"
      cpu = 10
      memory = 512
      essential = true
      portMappings = [
        {
          protocol = "tcp",
          containerPort = 80,
          hostPort = 0
        }
      ]
    }
  ])
  family = "scratch"
}
