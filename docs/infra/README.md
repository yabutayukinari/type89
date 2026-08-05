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
- staging はコスト最適化: **パブリックサブネット(NATなし)** / **ElastiCache 無し**(session/cache は RDS の database ドライバ＝本番ともバックエンドを揃えられる) / RDS 単一AZ。
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

> マイグレーション用のネットワーク設定(サブネット/SG)は CD が web サービスから自動取得するため、変数設定は不要です（環境を作り直しても壊れません）。

設定後、`make deploy`（= `.github/workflows/deploy-staging.yml` を **workflow_dispatch** で実行）。
→ ECR へ push → **migrate --force**（sessions/cache テーブル等を先に作成）→ ECS サービス更新、の順で自動。
自動 push トリガは付けていない（オンデマンドで環境が down の間に失敗しないため）。

---

## 3. フロント（Amplify）

`amplify_access_token` を渡していれば Amplify アプリ/ブランチ/ドメインが作成され、以後 **main への push で自動ビルド**。`app.<env_domain>` のドメイン検証は Route53 上で自動的に行われます（数分〜十数分）。

---

## オンデマンド運用（A: 本番同一構成を「使う時だけ」立てる）

STG の価値は**本番との同一性**にあるため、構成は本番と同じ（ALB/ECS/RDS/IAM/CD）まま、
**常時起動をやめて待機コストをほぼゼロ**にする。`infra/terraform/` の Makefile を使う。

```bash
cd infra/terraform

make up        # 本番同一構成を作成（初回は 10〜15 分。ACM/DNS 検証を含む）
make deploy    # CD(GitHub Actions) でビルド&デプロイ&migrate
make seed      # 初期データ投入（DB は毎回破棄されるため都度 seed する）

# ... テスト ...

make down      # 完全破棄。待機中の課金をほぼゼロにする
```

- **コスト**: `up` 中だけ課金（Fargate/ALB/RDS）。月に数時間なら**数百円**規模。使わない間は `down` で ~¥0。
- **同一性**: サイズ(1タスク / t4g.micro)は本番より小さくても、**構成・挙動(ALBのWSS・RDS接続・IAM/Secrets・CD経路)は本番と一致**。ここが検証で効く部分。
- **DB は毎回破棄**: staging はシード前提のため `make seed` で復元（永続データは持たない）。
- **注意（将来 prod と併用時）**: この構成は staging の破棄で GitHub OIDC プロバイダも一緒に消える。prod を常設で運用し始めたら、OIDC プロバイダ/ECR など長寿命リソースは別 state に分離するのが望ましい（当面 staging のみなら問題なし。deploy ロールの ARN は名前が同じなので作り直しても不変）。

## 動作確認

- API: `https://api.<env_domain>/api/health` が `{"status":"ok"}`
- フロント: `https://app.<env_domain>`
- WebSocket: フロントの入札画面でリアルタイム更新が届く（`wss://ws.<env_domain>`）

## コスト目安 / 片付け

- 常時起動なら月$40〜70（ALB / Fargate2 / RDS t4g.micro / Amplify）。
- **推奨は上記「オンデマンド運用(A)」**: `make up`〜`make down` で使う時だけ起動 → 待機中はほぼ¥0。月数時間の利用なら数百円規模。
- prod を作るときは `prod.tfvars`（OIDC は `create_oidc_provider=false` で既存参照）+ `backends/prod.hcl` で同じ手順。prod は常設想定。

## 補足・既知の注意点

- 本番向けには private サブネット+NAT、session/cache を database/redis(ElastiCache)、RDS Multi-AZ を推奨（`prod.tfvars.example` 参照）。
- Reverb のヘルスチェックは実測した専用エンドポイント `/up`(200) を使用。

### 初回 apply 時のライブ検証チェックリスト
実インフラでしか確認できない項目。`make up` → `make deploy` 後に確認する:
- [ ] `https://api.<env_domain>/api/health` が 200（ALB→web、ACM/TrustProxies が効いているか）
- [ ] ALB の reverb ターゲットが healthy（`/up` 200 で判定）
- [ ] フロントの入札画面で WSS がつながりリアルタイム更新が届く
- [ ] **Amplify が Next.js 16 の SSR をビルドできるか**（Amplify の Next 対応はバージョン追随が遅れることがある。失敗する場合は Amplify をやめ ECS で `next start` する構成に切替）
- [ ] Amplify の独自ドメイン(`app.<env_domain>`)が verified になるか（Route53 レコードが自動生成されない場合は手動追加）
- Terraform は `validate` 済み。実 `plan/apply` は各自の AWS 認証情報で実行（このリポジトリでは AWS 資源は作成していません）。
