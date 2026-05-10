# type_89

[![CI](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/ci.yml)
[![CodeQL](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/yabutayukinari/type89/actions/workflows/codeql.yml)
[![codecov](https://codecov.io/gh/yabutayukinari/type89/branch/main/graph/badge.svg)](https://codecov.io/gh/yabutayukinari/type89)
[![PHP](https://img.shields.io/badge/php-%5E8.4-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-13-ff2d20)](https://laravel.com/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

ユーザー管理を題材にした、Laravel / Filament の個人プラクティス用リポジトリです。Laravel Sail で完結するため、ローカルに PHP / Composer / Node を入れる必要はありません。

## 技術スタック

| 区分 | 内容 |
| --- | --- |
| 言語 | PHP 8.4+ |
| フレームワーク | Laravel 13 / Filament 5 / Sanctum 4 |
| フロントエンド | Vite 6 + Tailwind CSS 4 |
| データベース | MySQL（開発・本番） / SQLite インメモリ（テスト） |
| 実行環境 | Laravel Sail（Docker） |

## クイックスタート

必要なのは Docker（Docker Desktop / OrbStack 等）と `make` だけです。

```bash
make setup
```

`make help` で全ターゲットを確認できます。

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
