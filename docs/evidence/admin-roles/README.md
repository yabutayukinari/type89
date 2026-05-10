# 管理者ロール導入: 動作確認エビデンス

## 検証情報

- 検証日時: 2026-05-10 23:28 JST
- ブランチ: `feature/admin-roles`
- ベースコミット: `b611df8a269785b4e9a0e77b97ce3eb175926497`
- 検証環境: Laravel Sail (PHP 8.4 / MySQL 8 / Filament 5.6.2) on macOS
- ブラウザ: Google Chrome (1280×900 viewport)

## 検証用アカウント

| ロール | メール | パスワード |
|---|---|---|
| `system_admin` | sa@example.com | password |
| `general_admin` | ga@example.com | password |

## シナリオと結果

| # | シナリオ | 期待結果 | 実結果 | 画像 |
|---|---|---|---|---|
| 1 | `/admin` 未ログインアクセス → ログイン画面 | Filament ログイン画面が表示される | ○ ログイン画面表示 | [01_login_page.png](01_login_page.png) |
| 2 | `system_admin` でログイン → ダッシュボード | 認証成功、サイドナビに「管理者」「ユーザー」両方表示 | ○ 両方表示、`SA` アバター表示 | [02_system_admin_dashboard.png](02_system_admin_dashboard.png) |
| 3 | `system_admin` で `/admin/admins` 一覧 | 管理者一覧表示、`role` バッジ（システム管理者 / 一般管理者）が出る | ○ 2 件表示、両ロールバッジ確認 | [03_system_admin_admins_list.png](03_system_admin_admins_list.png) |
| 4 | `system_admin` で `/admin/users` 一覧 | ユーザー一覧アクセス可 | ○ 7 件表示、CRUD 操作可能 | [04_system_admin_users_list.png](04_system_admin_users_list.png) |
| 5 | `general_admin` でログイン → ダッシュボード | サイドナビに「管理者」が**表示されない** | ○ ナビは「ダッシュボード」「ユーザー」のみ、`GA` アバター | [05_general_admin_dashboard.png](05_general_admin_dashboard.png) |
| 6 | `general_admin` で `/admin/admins` 直接アクセス | HTTP 403 Forbidden | ○ `403 Forbidden` 画面表示 | [06_general_admin_admins_forbidden.png](06_general_admin_admins_forbidden.png) |
| 7 | `general_admin` で `/admin/users` 一覧 | ユーザー一覧アクセス可 | ○ 7 件表示 | [07_general_admin_users_list.png](07_general_admin_users_list.png) |

すべて期待結果と一致。

## 自動チェックの結果

```
$ make migrate
2026_05_10_000001_make_last_login_at_nullable_in_admins_table  DONE
2026_05_10_000002_add_role_to_admins_table  DONE

$ make test
PHPUnit 12.5.24 — Configuration: phpunit.xml
..........................   26 / 26 (100%)
OK (26 tests, 55 assertions)

$ make build
PHP CS Fixer: 0 issues
PHP CodeSniffer: 0 issues
PHPStan (Larastan): No errors
PHPMD: No violations
```

## 参考: 関連実装

- 認可: `App\Models\Admin::isSystemAdminLoggedIn()` (admin guard 経由で system_admin かを判定)
- AdminResource アクセス制御: `app/Filament/Resources/AdminResource.php` の `canAccess()`
- UserResource ForceDelete 制御: `app/Filament/Resources/UserResource.php` の `ForceDeleteBulkAction::visible()`

## 補足: ForceDelete 可視性の確認方法

UserResource の `ForceDeleteBulkAction` を「system_admin だけに表示」する制御は、UI 上では **trashed フィルタを切り替えた状態 + trashed レコードを選択した状態** で初めて可視判定が走ります。Filament 5 の `assertTableBulkActionVisible` は `BulkActionGroup` 内のアクションを直接参照できないため、本 PR では:

- `tests/Unit/Models/AdminTest.php` で `Admin::isSystemAdminLoggedIn()` の真偽を 3 ケース（system_admin / general_admin / 未ログイン）で担保
- ブラウザでの可視性差は今回のエビデンス対象外（ユニットテストでカバー）

としています。
