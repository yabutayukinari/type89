bucket         = "type89-tfstate-<ACCOUNT_ID>"
key            = "prod/terraform.tfstate"
region         = "ap-northeast-1"
dynamodb_table = "type89-tflock"
encrypt        = true
