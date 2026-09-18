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

## セットアップ（はじめての方へ）

用意するのは次の3つです。

- **Docker**（Docker Desktop / OrbStack 等） — バックエンド一式を動かします
- **make** — セットアップ／起動コマンド
- **Node.js**（20 以降を推奨） — フロントエンド（`frontend/`）はホストの Node で動かします

PHP・Composer・MySQL をローカルに入れる必要はありません（バックエンドはすべて Docker の中で完結します）。コマンドはリポジトリのルートで実行します。

### 1. 初期セットアップ（初回だけ）

```bash
make setup
```

コンテナ起動・`.env` 生成・DB マイグレーション & シード・フロントエンド依存のインストールまで、まとめて実行します（冪等なので何度実行しても安全）。

### 2. フロントエンド（Next.js）を起動 — 別ターミナル

```bash
make front
```

Next.js の開発サーバが立ち上がります（起動したままにします）。

### 3. リアルタイム機能を使う場合 — さらに別ターミナル

入札・落札通知などの WebSocket（Reverb）を動かすときだけ起動します。

```bash
make reverb
```

### 動作確認

| 項目 | URL / ログイン情報 |
| --- | --- |
| アプリ（ユーザー画面） | <http://localhost:3000> |
| 管理画面 | <http://localhost:3000/admin/login> |
| ログイン（一般ユーザー） | `test_user@example.com` / `test1111` |

`make front` / `make reverb` はそれぞれ起動しっぱなしになるため、別々のターミナルで実行してください。停止は各ターミナルで `Ctrl-C`。その他のコマンドは `make help` で確認できます。

## テスト

テストは Sail の `mysql.test` コンテナ（tmpfs マウントの MySQL）に対して実行します。`make setup` で `.env.testing` が生成されるので、追加のセットアップは不要です。

```bash
make test
```

`.env.testing` と `.env` はそれぞれ機密情報（APP_KEY 等）を含むため git 管理外（`.gitignore`）です。`make setup` 実行時に `.env.example` を雛形としてコピーし、`php artisan key:generate` で各環境固有の APP_KEY を生成します。

## コード品質

PHP のフォーマットは Laravel Pint（`laravel` プリセット + 必須の `declare(strict_types=1)`）です。静的解析は Larastan と PHPMD です。

```bash
make fix    # Pint で整形
make build  # pint --test + Larastan + PHPMD
```

## Git フック

`lefthook.yml` で pre-commit / pre-push を Sail 経由で管理しています（コンテナ起動が前提）。初回のみ:

```bash
brew install lefthook
lefthook install
```

## 依存の更新（Dependabot）の運用

Dependabot の PR は自動ではマージしません。人が内容を確かめてマージします。

必須チェック `Dependency freshness` は、`composer.lock` で動いたパッケージのうち次のいずれかがあれば落ちます。

- dev・alpha・beta・RC などの版
- メジャーの上げ（`0.x` 系は minor の上げをメジャー相当として扱う）
- Packagist の `published-time` で公開から 7 日未満の版

メジャーの判定は、下げなら通ります。安定度・公開7日未満・Packagist から取れないときの判定は、下げでもかかります。`composer.lock` の `time` フィールドは作者が偽装できるため判定に使わず、Packagist の `published-time` だけを見ます。Packagist から情報が取れなかった場合は不合格として扱います。

脆弱性修正など急いでマージしたい場合は、PR に `override-freshness` ラベルを付けると警告のみでチェックが通ります。

npm（`frontend/package-lock.json`）と GitHub Actions の依存はこのチェックの対象外です（npm 対応は第2段で追加予定）。当面は Dependabot の待機日数（cooldown）・`npm audit`・OSV-Scanner・人によるレビューでリスクを抑えます。

Dependabot の待機日数（`.github/dependabot.yml` の `cooldown`）は、semver の種類によらず一律 7 日です。cooldown の目的はサプライチェーン攻撃への備え（汚染されたバージョンが検知・取り下げられるまで待つ）であり、検知までのタイムラグは semver の種類に左右されないため、major だけを長くする扱いはやめました。

## Security

脆弱性を発見した場合は [GitHub Issues](https://github.com/yabutayukinari/type89/issues) でご報告ください。

## License

[MIT](LICENSE)
