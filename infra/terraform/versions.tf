terraform {
  required_version = ">= 1.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.60"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }

  # state は S3 + DynamoDB(ロック)。値は環境ごとに init 時 -backend-config で渡す。
  #   terraform init -backend-config=backends/staging.hcl
  backend "s3" {}
}

provider "aws" {
  region = var.region

  default_tags {
    tags = {
      Project     = "type89"
      Environment = var.environment
      ManagedBy   = "terraform"
    }
  }
}

# 現在のアカウント情報（ARN 組み立て等に使用）
data "aws_caller_identity" "current" {}

data "aws_availability_zones" "available" {
  state = "available"
}

locals {
  name = "type89-${var.environment}"

  # サブドメイン（例: api.stg.example.com / ws.stg.example.com / app.stg.example.com）
  api_fqdn = "${var.subdomain_api}.${var.env_domain}"
  ws_fqdn  = "${var.subdomain_ws}.${var.env_domain}"
  app_fqdn = "${var.subdomain_app}.${var.env_domain}"
}
