# type_89

[![CI](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml)
[![CodeQL](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml)
[![codecov](https://codecov.io/gh/yabutayukinari/type89/branch/main/graph/badge.svg)](https://codecov.io/gh/yabutayukinari/type89)
[![PHP](https://img.shields.io/badge/php-%5E8.4-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-13-ff2d20)](https://laravel.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

ユーザー管理・オークションを題材にした、Laravel API + Next.js SPA の個人プラクティス用リポジトリです。バックエンドは Laravel Sail で完結するため、ローカルに PHP / Composer は不要です（フロントエンドの `frontend/` のみ Node が必要）。

## 技術スタック

| 区分 | 内容 |
| --- | --- |
| 言語 | PHP 8.4+ |
| バックエンド | Laravel 13 / Sanctum 4（SPA 認証）/ Reverb 1（WebSocket） |
| フロントエンド | Next.js（`frontend/` に分離） |
| データベース | MySQL（開発）/ MySQL 8 on tmpfs（テスト） |
| 実行環境 | Laravel Sail（Docker） |

## クイックスタート

### 1. バックエンド（Laravel / Sail）

必要なのは Docker（Docker Desktop / OrbStack 等）と `make` だけです。

```bash
make setup
```

`.env` 生成・Sail 起動・マイグレーション・シードまで冪等に実行します（`make help` で全ターゲット）。完了後:

- アプリ: <http://localhost>
- 管理画面: <http://localhost/admin>
- ログイン: `test_user@example.com` / `test1111`

### 2. フロントエンド（Next.js）

`frontend/` は Sail とは別に、ホストの Node で起動します。

```bash
cd frontend
cp .env.example .env.local   # NEXT_PUBLIC_* の雛形（API / Reverb の接続先）
npm install
npm run dev                  # http://localhost:3000
```

### 3. リアルタイム（Reverb）

入札・落札通知などの WebSocket 機能を使うには、別ターミナルで Reverb サーバを起動します。

```bash
make reverb
```

## テスト

テストは Sail の `mysql.test` コンテナ（tmpfs マウントの MySQL）に対して実行します。`make setup` で `.env.testing` が生成されるので、追加のセットアップは不要です。

```bash
make test
```

`.env.testing` と `.env` はそれぞれ機密情報（APP_KEY 等）を含むため git 管理外（`.gitignore`）です。`make setup` 実行時に `.env.example` を雛形としてコピーし、`php artisan key:generate` で各環境固有の APP_KEY を生成します。

## Git フック

`lefthook.yml` で pre-commit / pre-push を Sail 経由で管理しています（コンテナ起動が前提）。初回のみ:

```bash
brew install lefthook
lefthook install
```

## Security

脆弱性を発見した場合は [GitHub Issues](https://github.com/yabutayukinari/type89/issues) でご報告ください。

## License

[MIT](LICENSE)
