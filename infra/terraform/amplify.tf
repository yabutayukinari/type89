# フロントエンド (Next.js) は AWS Amplify Hosting でホスト + 自動CD。
# GitHub 接続用 PAT (amplify_access_token) を渡したときだけ作成する。
locals {
  amplify_enabled = var.amplify_access_token != "" && var.amplify_repository_url != ""
}

resource "aws_amplify_app" "frontend" {
  count = local.amplify_enabled ? 1 : 0

  name         = "${local.name}-frontend"
  repository   = var.amplify_repository_url
  access_token = var.amplify_access_token
  platform     = "WEB_COMPUTE" # Next.js SSR

  # frontend/ サブディレクトリをビルド
  build_spec = <<-YAML
    version: 1
    applications:
      - appRoot: frontend
        frontend:
          phases:
            preBuild:
              commands:
                - npm ci
            build:
              commands:
                - npm run build
          artifacts:
            baseDirectory: .next
            files:
              - '**/*'
          cache:
            paths:
              - node_modules/**/*
  YAML

  environment_variables = {
    NEXT_PUBLIC_API_URL        = "https://${local.api_fqdn}"
    NEXT_PUBLIC_REVERB_APP_KEY = random_password.reverb_app_key.result
    NEXT_PUBLIC_REVERB_HOST    = local.ws_fqdn
    NEXT_PUBLIC_REVERB_PORT    = "443"
    NEXT_PUBLIC_REVERB_SCHEME  = "https"
  }
}

resource "aws_amplify_branch" "main" {
  count = local.amplify_enabled ? 1 : 0

  app_id      = aws_amplify_app.frontend[0].id
  branch_name = "main"
  stage       = var.environment == "prod" ? "PRODUCTION" : "DEVELOPMENT"

  enable_auto_build = true
}

# app.<env_domain> を Amplify ブランチに紐付け
resource "aws_amplify_domain_association" "frontend" {
  count = local.amplify_enabled ? 1 : 0

  app_id      = aws_amplify_app.frontend[0].id
  domain_name = var.root_domain

  sub_domain {
    branch_name = aws_amplify_branch.main[0].branch_name
    prefix      = trimsuffix("${var.subdomain_app}.${var.env_domain}", ".${var.root_domain}")
  }
}
