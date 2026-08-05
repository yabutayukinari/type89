# terraform init -backend-config=backends/staging.hcl で使用。
# <ACCOUNT_ID> は実値に置換（bootstrap 手順で作成する S3/DynamoDB 名に合わせる）。
bucket         = "type89-tfstate-<ACCOUNT_ID>"
key            = "staging/terraform.tfstate"
region         = "ap-northeast-1"
dynamodb_table = "type89-tflock"
encrypt        = true
