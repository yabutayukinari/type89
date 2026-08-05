output "ecr_repository_url" {
  description = "アプリイメージの push 先"
  value       = aws_ecr_repository.app.repository_url
}

output "ecs_cluster_name" {
  value = aws_ecs_cluster.main.name
}

output "ecs_web_service" {
  value = aws_ecs_service.web.name
}

output "ecs_reverb_service" {
  value = aws_ecs_service.reverb.name
}

output "web_task_family" {
  value = aws_ecs_task_definition.web.family
}

output "alb_dns_name" {
  value = aws_lb.main.dns_name
}

output "api_url" {
  value = "https://${local.api_fqdn}"
}

output "ws_url" {
  value = "wss://${local.ws_fqdn}"
}

output "app_url" {
  value = "https://${local.app_fqdn}"
}

output "github_deploy_role_arn" {
  description = "GitHub Actions が AssumeRole する ARN。CD の secrets/vars に設定する。"
  value       = aws_iam_role.github_deploy.arn
}

output "private_subnet_ids" {
  description = "ECS/RDS が使うサブネット (staging はパブリック)"
  value       = aws_subnet.public[*].id
}

output "web_security_group_id" {
  value = aws_security_group.web.id
}

output "app_ssm_prefix" {
  description = "アプリ用 SSM パラメータの接頭辞"
  value       = "/${local.name}"
}
