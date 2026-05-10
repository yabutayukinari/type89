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

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Git フック (Lefthook)

`lefthook.yml` で pre-commit / pre-push フックを管理しています。初回のみ以下を実行してください。

```bash
brew install lefthook    # 未導入の場合
lefthook install         # .git/hooks にフックを登録
```

- **pre-commit**: ステージ済み PHP ファイルに `php-cs-fixer fix` と `phpcs` を実行
- **pre-push**: `composer build`（csf + cs + sa + md）でフル静的解析

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
