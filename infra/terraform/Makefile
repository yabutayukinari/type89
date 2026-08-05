# オンデマンド運用(A): 本番と同一構成を「使う時だけ」立てて、終わったら壊す。
# 使い方:
#   make up       # 環境を作成/更新
#   make deploy   # CD(GitHub Actions)でビルド&デプロイ&migrate
#   make seed     # 初期データ投入
#   ... テスト ...
#   make down     # 完全破棄（待機コストをほぼゼロに）
#
# 前提: AWS CLI 認証済み / gh 認証済み / jq / terraform / backend(S3,DynamoDB)作成済み。
# 別環境は ENV=prod のように指定。

ENV ?= staging
TF  := terraform

.PHONY: help init plan up down output deploy migrate seed

help: ## ターゲット一覧
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-10s\033[0m %s\n",$$1,$$2}'

init: ## terraform init (backend は ENV ごと)
	$(TF) init -backend-config=backends/$(ENV).hcl

plan: init ## 変更内容を確認
	$(TF) plan -var-file=$(ENV).tfvars

up: init ## 本番同一構成をオンデマンドで作成/更新
	$(TF) apply -var-file=$(ENV).tfvars
	@echo ""
	@echo "==> 次: make deploy (ビルド&デプロイ&migrate) → make seed (初期データ)"

down: ## 環境を完全破棄（待機コストをほぼゼロに）
	$(TF) destroy -var-file=$(ENV).tfvars

output: ## terraform output
	$(TF) output

deploy: ## CD(GitHub Actions)をトリガしてビルド&デプロイ&migrate
	gh workflow run deploy-staging.yml --ref main
	@echo "==> gh run watch でデプロイを追跡できます"

migrate: ## 単発タスクで migrate --force
	./scripts/oneoff.sh php artisan migrate --force

seed: ## 単発タスクで db:seed --force（DBは毎回破棄される前提の初期化）
	./scripts/oneoff.sh php artisan db:seed --force
