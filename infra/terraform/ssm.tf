# 機密値を生成し SSM Parameter Store(SecureString) に格納。
# ECS タスク定義から secrets として参照する。

# Laravel APP_KEY (base64:<32bytes>)
resource "random_id" "app_key" {
  byte_length = 32
}

# Reverb の認証情報
resource "random_id" "reverb_app_id" {
  byte_length = 6
}
resource "random_password" "reverb_app_key" {
  length  = 20
  special = false
}
resource "random_password" "reverb_app_secret" {
  length  = 32
  special = false
}

locals {
  app_key = "base64:${random_id.app_key.b64_std}"

  # SSM に入れる機密パラメータ (name => value)
  secure_params = {
    "APP_KEY"           = local.app_key
    "DB_PASSWORD"       = random_password.db.result
    "REVERB_APP_ID"     = random_id.reverb_app_id.dec
    "REVERB_APP_KEY"    = random_password.reverb_app_key.result
    "REVERB_APP_SECRET" = random_password.reverb_app_secret.result
  }
}

resource "aws_ssm_parameter" "secure" {
  for_each = local.secure_params

  name  = "/${local.name}/${each.key}"
  type  = "SecureString"
  value = each.value
}
