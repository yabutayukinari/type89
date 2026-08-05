locals {
  ecr_image = "${aws_ecr_repository.app.repository_url}:${var.app_image_tag}"

  # 平文で渡してよいアプリ環境変数。
  # セッション/キャッシュは RDS(database) を使う。ElastiCache を足さずに複数タスク間で
  # 状態を共有でき、本番とも同じバックエンドに揃えられる（パリティ確保）。
  # 必要なテーブルは migration (sessions / cache) で作成される。
  app_environment = {
    APP_NAME                 = "type89"
    APP_ENV                  = var.app_env
    APP_DEBUG                = "false"
    APP_URL                  = "https://${local.api_fqdn}"
    LOG_CHANNEL              = "stderr"
    DB_CONNECTION            = "mysql"
    DB_HOST                  = aws_db_instance.main.address
    DB_PORT                  = "3306"
    DB_DATABASE              = var.db_name
    DB_USERNAME              = var.db_username
    BROADCAST_DRIVER         = "reverb"
    QUEUE_CONNECTION         = "sync"
    CACHE_DRIVER             = "database"
    SESSION_DRIVER           = "database"
    SESSION_SECURE_COOKIE    = "true"
    SESSION_DOMAIN           = ".${var.env_domain}"
    SANCTUM_STATEFUL_DOMAINS = local.app_fqdn
    FRONTEND_URL             = "https://${local.app_fqdn}"
    REVERB_HOST              = local.ws_fqdn
    REVERB_PORT              = "443"
    REVERB_SCHEME            = "https"
    REVERB_SERVER_HOST       = "0.0.0.0"
    REVERB_SERVER_PORT       = "8080"
  }

  container_environment = [for k, v in local.app_environment : { name = k, value = tostring(v) }]
  container_secrets     = [for k, p in aws_ssm_parameter.secure : { name = k, valueFrom = p.arn }]
}

resource "aws_ecs_cluster" "main" {
  name = local.name
}

resource "aws_cloudwatch_log_group" "app" {
  name              = "/ecs/${local.name}"
  retention_in_days = 14
}

# ---- IAM ----
data "aws_iam_policy_document" "ecs_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# 実行ロール: ECR pull / SSM 読取 / ログ出力
resource "aws_iam_role" "execution" {
  name               = "${local.name}-exec"
  assume_role_policy = data.aws_iam_policy_document.ecs_assume.json
}

resource "aws_iam_role_policy_attachment" "execution_managed" {
  role       = aws_iam_role.execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

data "aws_iam_policy_document" "exec_ssm" {
  statement {
    actions   = ["ssm:GetParameters", "ssm:GetParameter"]
    resources = [for p in aws_ssm_parameter.secure : p.arn]
  }

  # SecureString の復号。SSM 経由に限定（既定 aws/ssm キーでも CMK でも動くように）。
  statement {
    actions   = ["kms:Decrypt"]
    resources = ["*"]
    condition {
      test     = "StringEquals"
      variable = "kms:ViaService"
      values   = ["ssm.${var.region}.amazonaws.com"]
    }
  }
}

resource "aws_iam_role_policy" "exec_ssm" {
  name   = "ssm-read"
  role   = aws_iam_role.execution.id
  policy = data.aws_iam_policy_document.exec_ssm.json
}

# タスクロール (アプリ実行時の権限。現状は最小)
resource "aws_iam_role" "task" {
  name               = "${local.name}-task"
  assume_role_policy = data.aws_iam_policy_document.ecs_assume.json
}

# ---- タスク定義 ----
resource "aws_ecs_task_definition" "web" {
  family                   = "${local.name}-web"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.web_cpu
  memory                   = var.web_memory
  execution_role_arn       = aws_iam_role.execution.arn
  task_role_arn            = aws_iam_role.task.arn

  runtime_platform {
    cpu_architecture        = "X86_64"
    operating_system_family = "LINUX"
  }

  container_definitions = jsonencode([
    {
      name         = "web"
      image        = local.ecr_image
      essential    = true
      portMappings = [{ containerPort = 80 }]
      environment  = local.container_environment
      secrets      = local.container_secrets
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.app.name
          "awslogs-region"        = var.region
          "awslogs-stream-prefix" = "web"
        }
      }
    }
  ])
}

resource "aws_ecs_task_definition" "reverb" {
  family                   = "${local.name}-reverb"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.reverb_cpu
  memory                   = var.reverb_memory
  execution_role_arn       = aws_iam_role.execution.arn
  task_role_arn            = aws_iam_role.task.arn

  runtime_platform {
    cpu_architecture        = "X86_64"
    operating_system_family = "LINUX"
  }

  container_definitions = jsonencode([
    {
      name         = "reverb"
      image        = local.ecr_image
      essential    = true
      command      = ["/usr/local/bin/entrypoint-reverb.sh"]
      portMappings = [{ containerPort = 8080 }]
      environment  = local.container_environment
      secrets      = local.container_secrets
      logConfiguration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = aws_cloudwatch_log_group.app.name
          "awslogs-region"        = var.region
          "awslogs-stream-prefix" = "reverb"
        }
      }
    }
  ])
}

# ---- サービス ----
resource "aws_ecs_service" "web" {
  name            = "${local.name}-web"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.web.arn
  desired_count   = var.web_desired_count
  launch_type     = "FARGATE"

  # 起動時のキャッシュ生成/パッケージ探索で数十秒かかるため、その間は
  # ヘルスチェック不合格でタスクを kill しない（クラッシュループ防止）。
  health_check_grace_period_seconds = 120

  network_configuration {
    subnets          = aws_subnet.public[*].id
    security_groups  = [aws_security_group.web.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.web.arn
    container_name   = "web"
    container_port   = 80
  }

  # CD が新しいタスク定義リビジョンを登録・デプロイするため、image 差分での競合を避ける
  lifecycle {
    ignore_changes = [task_definition]
  }

  depends_on = [aws_lb_listener.https]
}

resource "aws_ecs_service" "reverb" {
  name            = "${local.name}-reverb"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.reverb.arn
  desired_count   = 1
  launch_type     = "FARGATE"

  health_check_grace_period_seconds = 120

  network_configuration {
    subnets          = aws_subnet.public[*].id
    security_groups  = [aws_security_group.reverb.id]
    assign_public_ip = true
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.reverb.arn
    container_name   = "reverb"
    container_port   = 8080
  }

  lifecycle {
    ignore_changes = [task_definition]
  }

  depends_on = [aws_lb_listener.https]
}
