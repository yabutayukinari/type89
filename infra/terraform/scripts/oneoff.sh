#!/usr/bin/env bash
# web タスク定義をベースに、コマンドを差し替えた単発 ECS タスクを実行する。
# 例: ./scripts/oneoff.sh php artisan migrate --force
#     ./scripts/oneoff.sh php artisan db:seed --force
#
# 前提: infra/terraform/ で terraform apply 済み、AWS CLI 認証済み、jq あり。
set -euo pipefail

cd "$(dirname "$0")/.."

CLUSTER=$(terraform output -raw ecs_cluster_name)
SERVICE=$(terraform output -raw ecs_web_service)
FAMILY=$(terraform output -raw web_task_family)

# 引数を JSON 配列(command)に変換
CMD_JSON=$(printf '%s\n' "$@" | jq -R . | jq -s .)

TD=$(aws ecs describe-task-definition --task-definition "$FAMILY")
NEW=$(echo "$TD" | jq --argjson cmd "$CMD_JSON" '
  .taskDefinition
  | .containerDefinitions[0].command = $cmd
  | {family, taskRoleArn, executionRoleArn, networkMode, containerDefinitions,
     requiresCompatibilities, cpu, memory, runtimePlatform}')
REV=$(aws ecs register-task-definition --cli-input-json "$NEW" \
      --query 'taskDefinition.taskDefinitionArn' --output text)

# ネットワークは web サービスから取得（作り直しても壊れない）
NET=$(aws ecs describe-services --cluster "$CLUSTER" --services "$SERVICE" \
      --query 'services[0].networkConfiguration.awsvpcConfiguration' --output json)
SUBNETS=$(echo "$NET" | jq -r '.subnets | join(",")')
SGS=$(echo "$NET" | jq -r '.securityGroups | join(",")')

ARN=$(aws ecs run-task --cluster "$CLUSTER" --launch-type FARGATE \
      --task-definition "$REV" \
      --network-configuration "awsvpcConfiguration={subnets=[$SUBNETS],securityGroups=[$SGS],assignPublicIp=ENABLED}" \
      --query 'tasks[0].taskArn' --output text)
echo "task: $ARN"

aws ecs wait tasks-stopped --cluster "$CLUSTER" --tasks "$ARN"
CODE=$(aws ecs describe-tasks --cluster "$CLUSTER" --tasks "$ARN" \
       --query 'tasks[0].containers[0].exitCode' --output text)
echo "exit code: $CODE"
test "$CODE" = "0"
