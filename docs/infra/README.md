# AWS staging 環境 + CD 構築手順

Laravel API(+Reverb) を **ECS Fargate**、フロント(Next.js) を **Amplify Hosting** に載せ、
**GitHub Actions(OIDC)** で main マージ時に自動デプロイする構成。IaC は **Terraform**。

```
                 ┌──────────── Route53 (example.com) ───────────┐
                 │  api.stg  ws.stg → ALB      app.stg → Amplify │
                 └───────────────────────────────────────────────┘
 GitHub(main) ──push──> Amplify(Next SSR)                 [フロント]
 GitHub(main) ──OIDC──> Actions ─ECR push→ ECS更新→ migrate  [バックエンド]
                                     │
              ALB(HTTPS/WSS) ─ web(nginx+php-fpm) / reverb(WS) ─ RDS(MySQL)
```

構成の詳細:
- 常時起動が要る **Reverb(WebSocket)** があるためサーバレスは不採用。ECS Fargate 常駐 + ALB(WSS)。
- staging はコスト最適化: **パブリックサブネット(NATなし)** / **ElastiCache 無し**(session=cookie, cache=file) / RDS 単一AZ。
- 機密は **SSM Parameter Store(SecureString)**、ECS が secrets として注入。
- CD は **OIDC**(長期キー無し)。

---

## 0. 前提（1回だけ・手動）

1. **AWS アカウント**（課金可能）。
2. **ドメイン**を Route53 で用意
   - 新規取得: マネジメントコンソール/CLI の Route53 Domains で登録（`aws route53domains register-domain`。支払い・連絡先・数十分の承認が絡むため手動が実務）。
   - 取得済み or 既存を委任する場合は、`root_domain` の **Hosted Zone** が Route53 に存在する状態にする（Terraform はこの Zone を参照する）。
3. **Terraform state 用のバックエンド**（S3 + DynamoDB）を作成:
   ```bash
   ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
   REGION=ap-northeast-1
   aws s3api create-bucket --bucket "type89-tfstate-$ACCOUNT_ID" \
     --region $REGION --create-bucket-configuration LocationConstraint=$REGION
   aws s3api put-bucket-versioning --bucket "type89-tfstate-$ACCOUNT_ID" \
     --versioning-configuration Status=Enabled
   aws dynamodb create-table --table-name type89-tflock \
     --attribute-definitions AttributeName=LockID,AttributeType=S \
     --key-schema AttributeName=LockID,KeyType=HASH \
     --billing-mode PAY_PER_REQUEST --region $REGION
   ```
   作成した名前を `infra/terraform/backends/staging.hcl` の `<ACCOUNT_ID>` に反映。

---

## 1. Terraform で staging を作成

```bash
cd infra/terraform

cp staging.tfvars.example staging.tfvars
# staging.tfvars を編集: root_domain / env_domain を自分のドメインに。
# フロントも作るなら amplify_access_token(GitHub PAT, repo scope) を用意し、
#   export TF_VAR_amplify_access_token=ghp_xxx  で注入（tfvars に直書きしない）。

terraform init -backend-config=backends/staging.hcl
terraform plan  -var-file=staging.tfvars
terraform apply -var-file=staging.tfvars
```

> 初回 apply 時点では ECS はイメージ(`bootstrap` タグ)が無いため unhealthy です。次の CD で実イメージが入ると healthy になります。

apply 後、出力を控える:
```bash
terraform output
```

---

## 2. CD（GitHub Actions）を設定

GitHub リポジトリ **Settings → Secrets and variables → Actions → Variables** に、`terraform output` の値を登録:

| Variable | 取得元(output) |
| --- | --- |
| `AWS_REGION` | `ap-northeast-1` |
| `AWS_DEPLOY_ROLE_ARN` | `github_deploy_role_arn` |
| `ECR_REPOSITORY` | `ecr_repository_url` の末尾リポジトリ名（`type89-staging-app`） |
| `ECS_CLUSTER` | `ecs_cluster_name` |
| `ECS_WEB_SERVICE` | `ecs_web_service` |
| `ECS_REVERB_SERVICE` | `ecs_reverb_service` |
| `ECS_WEB_TASK_FAMILY` | `web_task_family` |
| `ECS_REVERB_TASK_FAMILY` | `type89-staging-reverb` |
| `ECS_SUBNETS` | `private_subnet_ids` をカンマ区切りで |
| `ECS_SECURITY_GROUP` | `web_security_group_id` |

設定後、`.github/workflows/deploy-staging.yml` を **workflow_dispatch** で手動実行（or main へ push）。
→ ECR へイメージ push → ECS 更新 → `migrate --force` まで自動。

---

## 3. フロント（Amplify）

`amplify_access_token` を渡していれば Amplify アプリ/ブランチ/ドメインが作成され、以後 **main への push で自動ビルド**。`app.<env_domain>` のドメイン検証は Route53 上で自動的に行われます（数分〜十数分）。

---

## 動作確認

- API: `https://api.<env_domain>/api/health` が `{"status":"ok"}`
- フロント: `https://app.<env_domain>`
- WebSocket: フロントの入札画面でリアルタイム更新が届く（`wss://ws.<env_domain>`）

## コスト目安 / 片付け

- 目安 月$40〜70（ALB / Fargate2 / RDS t4g.micro / Amplify）。使わないときは `terraform destroy -var-file=staging.tfvars` で破棄。
- prod を作るときは `prod.tfvars`（OIDC は `create_oidc_provider=false` で既存参照）+ `backends/prod.hcl` で同じ手順。

## 補足・既知の注意点

- 本番向けには private サブネット+NAT、session/cache を database/redis(ElastiCache)、RDS Multi-AZ を推奨（`prod.tfvars.example` 参照）。
- Reverb のヘルスチェックは matcher を広め(`200-499`)にしている。実挙動に合わせて調整余地あり。
- Terraform は `validate` 済み。実 `plan/apply` は各自の AWS 認証情報で実行（このリポジトリでは AWS 資源は作成していません）。
