variable "region" {
  description = "AWS リージョン"
  type        = string
  default     = "ap-northeast-1"
}

variable "environment" {
  description = "環境名 (staging / prod)"
  type        = string
}

# ---- ドメイン ----
variable "root_domain" {
  description = "Route53 で管理するルートドメイン (例: example.com)。Hosted Zone は事前に存在させておく。"
  type        = string
}

variable "env_domain" {
  description = "この環境で使うドメイン (例: stg.example.com)。root_domain のサブドメイン。"
  type        = string
}

variable "subdomain_api" {
  description = "API(web) のサブドメインラベル"
  type        = string
  default     = "api"
}

variable "subdomain_ws" {
  description = "Reverb(WebSocket) のサブドメインラベル"
  type        = string
  default     = "ws"
}

variable "subdomain_app" {
  description = "フロント(Amplify) のサブドメインラベル"
  type        = string
  default     = "app"
}

# ---- ネットワーク ----
variable "vpc_cidr" {
  description = "VPC の CIDR"
  type        = string
  default     = "10.20.0.0/16"
}

variable "az_count" {
  description = "使用する AZ 数 (ALB/RDS は最低2)"
  type        = number
  default     = 2
}

# ---- ECS / アプリ ----
variable "app_image_tag" {
  description = "デプロイするアプリイメージのタグ (CD が Git SHA を渡す)。初回 apply 前は 'bootstrap' などのダミーで可。"
  type        = string
  default     = "bootstrap"
}

variable "web_cpu" {
  description = "web タスクの CPU (Fargate 単位)"
  type        = number
  default     = 256
}

variable "web_memory" {
  description = "web タスクのメモリ(MiB)"
  type        = number
  default     = 512
}

variable "web_desired_count" {
  description = "web サービスの希望タスク数"
  type        = number
  default     = 1
}

variable "reverb_cpu" {
  type    = number
  default = 256
}

variable "reverb_memory" {
  type    = number
  default = 512
}

# ---- RDS ----
variable "db_instance_class" {
  description = "RDS インスタンスクラス"
  type        = string
  default     = "db.t4g.micro"
}

variable "db_allocated_storage" {
  type    = number
  default = 20
}

variable "db_name" {
  type    = string
  default = "type89"
}

variable "db_username" {
  type    = string
  default = "type89"
}

variable "db_multi_az" {
  description = "RDS を Multi-AZ にするか (staging は false 推奨)"
  type        = bool
  default     = false
}

# ---- アプリ環境変数（SSM に格納する非機密のデフォルト。機密は別途 SecureString で投入）----
variable "app_env" {
  description = "Laravel の APP_ENV"
  type        = string
  default     = "staging"
}

# ---- CI/CD (GitHub OIDC) ----
variable "github_owner" {
  description = "GitHub オーナー名"
  type        = string
  default     = "yabutayukinari"
}

variable "github_repo" {
  description = "GitHub リポジトリ名"
  type        = string
  default     = "type89"
}

variable "github_deploy_ref" {
  description = "デプロイを許可する ref 条件 (例: refs/heads/main)"
  type        = string
  default     = "refs/heads/main"
}

# ---- フロント (Amplify) ----
variable "amplify_repository_url" {
  description = "Amplify に接続する GitHub リポジトリ URL (例: https://github.com/owner/repo)"
  type        = string
  default     = ""
}

variable "amplify_access_token" {
  description = "Amplify が GitHub に接続するための PAT (SecureString で渡す。空なら Amplify リソースを作らない)"
  type        = string
  default     = ""
  sensitive   = true
}
