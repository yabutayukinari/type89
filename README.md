# type_89

[![CI](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml)
[![CodeQL](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml)
[![codecov](https://codecov.io/gh/yabutayukinari/type89/branch/main/graph/badge.svg)](https://codecov.io/gh/yabutayukinari/type89)
[![PHP](https://img.shields.io/badge/php-%5E8.4-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-13-ff2d20)](https://laravel.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Laravel をベースにしたユーザー管理アプリケーションのプラクティス用リポジトリです。

## 技術スタック

- PHP 8.4+
- Laravel 13
- Filament 5
- Laravel Sanctum 4
- MySQL（開発・本番） / SQLite インメモリ（テスト）
- Vite

## セットアップ

ローカルに PHP / composer / Node を入れる必要はありません。Docker（Docker Desktop / OrbStack 等）だけ用意してください。

```bash
# 1. 依存をインストール（PHP/composer をコンテナで実行）
docker run --rm \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php84-composer:latest \
  composer install --ignore-platform-reqs

# 2. 環境ファイルをコピー
cp .env.example .env

# 3. Sail 起動（PHP / MySQL / Redis のコンテナ群を立ち上げ）
./vendor/bin/sail up -d

# 4. コンテナ内でアプリ初期化
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
```

以降は `./vendor/bin/sail <cmd>` で各種ツールを実行できます。よく使うエイリアスを `~/.zshrc` に入れておくと楽です。

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

### Git フック (Lefthook)

`lefthook.yml` で pre-commit / pre-push フックを管理しています。フック内のコマンドはすべて Sail 経由でコンテナ内実行されるため、ローカルに PHP は不要です（コンテナが起動している必要はあります）。

初回のみ:

```bash
brew install lefthook    # 未導入の場合
lefthook install         # .git/hooks にフックを登録
```

- **pre-commit**: ステージ済み PHP ファイルに `sail bin php-cs-fixer fix` と `sail bin phpcs` を実行
- **pre-push**: `sail composer build`（csf + cs + sa + md）でフル静的解析

> フック実行前に `./vendor/bin/sail up -d` でコンテナを起動しておいてください。停止中だとフックが失敗します。

## 開発

```bash
npm run dev         # 開発ビルド（watch）
npm run build       # 本番ビルド
```

## テスト

`phpunit.xml` で SQLite インメモリを使用するよう設定されているため、追加のセットアップなしでテストを実行できます。

```bash
composer test                            # 全テスト
./vendor/bin/phpunit --filter=ClassName  # 特定クラス
```

### MySQL を利用した結合テストを行う場合

`.env.testing` の DB 設定に合わせて、MySQL 側にデータベースとユーザーを用意してください。

```sql
CREATE SCHEMA testing;
CREATE USER 'sail'@'%' IDENTIFIED BY 'password';
GRANT ALL ON testing.* TO 'sail'@'%';
```

## コード品質

| コマンド | 内容 |
| --- | --- |
| `composer csf` | PHP CS Fixer（dry-run） |
| `composer csf-fix` | PHP CS Fixer（自動修正） |
| `composer cs` | PHP CodeSniffer |
| `composer cs-fix` | PHP CodeSniffer（自動修正） |
| `composer sa` | Larastan / PHPStan |
| `composer md` | PHPMD |
| `composer build` | csf + cs + sa + md |
| `composer tests` | build + test |

コミット前に `composer tests` がグリーンになることを確認してください。
