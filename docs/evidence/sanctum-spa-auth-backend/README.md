# PR 2: Sanctum SPA 認証 (バックエンド) 動作確認

`web` (User) と `admin` (Admin) の Multi-guard を Sanctum SPA 認証で同時に扱える状態を作った。CSRF cookie → login → me → logout の一連のフローを curl で再現できることを確認する。

## 環境

- Laravel API: `http://localhost` (Sail コンテナ)
- ブラウザ想定 Origin: `http://localhost:3000`
- DB: `migrate:fresh` 直後にテストユーザー / 管理者を 1 件ずつ作成
  - User: `user@example.com` / `password`
  - Admin: `admin@example.com` / `password` (role: `system_admin`)

## 設定

- `config/cors.php`: `allowed_origins` を `[FRONTEND_URL]`、`supports_credentials` を `true` に
- `config/sanctum.php`: `stateful` のデフォルトに `localhost:3000` を含む (既存)
- `app/Http/Kernel.php`: `api` middleware に `EnsureFrontendRequestsAreStateful` を追加
- `.env`: `SESSION_DOMAIN=localhost` / `FRONTEND_URL=http://localhost:3000` / `SANCTUM_STATEFUL_DOMAINS=localhost:3000`

## API

```
GET    /api/health        — health check (認証不要)
POST   /api/login         — User ログイン (web guard セッションを開始)
POST   /api/logout        — User ログアウト (auth:web)
GET    /api/me            — User 自身を返す (auth:web)
POST   /api/admin/login   — Admin ログイン (admin guard セッションを開始)
POST   /api/admin/logout  — Admin ログアウト (auth:admin)
GET    /api/admin/me      — Admin 自身を返す (auth:admin)
```

## E2E 動作確認 (User フロー)

```
== 1. CSRF cookie ==
HTTP 204
XSRF-TOKEN length=340

== 2. POST /api/login (User) ==
{"data":{"id":1,"name":"Louvenia Zemlak","email":"user@example.com"}}
HTTP 200

== 3. GET /api/me ==
{"data":{"id":1,"name":"Louvenia Zemlak","email":"user@example.com"}}
HTTP 200

== 4. POST /api/logout ==
HTTP 204

== 5. GET /api/me (after logout) ==
{"message":"Unauthenticated."}
HTTP 401
```

## E2E 動作確認 (Admin フロー + クロスガード分離)

```
== Admin CSRF ==
HTTP 204

== Admin login ==
{"data":{"id":1,"name":"Sterling Goyette","email":"admin@example.com","role":"system_admin"}}
HTTP 200

== Admin me ==
{"data":{"id":1,"name":"Sterling Goyette","email":"admin@example.com","role":"system_admin"}}
HTTP 200

== User /api/me on admin session (should be 401) ==
{"message":"Unauthenticated."}
HTTP 401

== Login fail (wrong password) ==
{"message":"These credentials do not match our records.","errors":{"email":["These credentials do not match our records."]}}
HTTP 422
```

`web` セッションで `/api/admin/me` にアクセスすると 401、`admin` セッションで `/api/me` にアクセスしても 401 となるため、**ガードはセッション内で完全に分離されている**。これで PR 3 で Next.js 側を 2 系統のログイン UI に分けて実装できる。

## 自動テスト

`tests/Feature/Api/AuthTest.php` (User) と `tests/Feature/Api/Admin/AuthTest.php` (Admin) で以下を網羅:

- ログイン成功 (リソース返却 + `assertAuthenticatedAs`)
- ログイン失敗 (422 + email バリデーションエラー)
- バリデーション (email / password 必須)
- 未認証時 `/api/me` は 401
- 認証済み `/api/me` で自分を返す
- ログアウトでセッション破棄
- 未認証時 `/api/logout` は 401
- 管理者: web セッションでは `/api/admin/me` にアクセス不可

```
$ ./vendor/bin/sail composer test
PHPUnit 12.5.24 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.21
..................                                                18 / 18 (100%)
Time: 00:00.818, Memory: 56.50 MB
OK (18 tests, 41 assertions)
```

## 後続 PR への申し送り

- フロント側 (PR 3) は axios で `withCredentials: true` を立てて、まず GET `/sanctum/csrf-cookie` → POST `/api/login` の順で叩く
- ログイン後のセッション維持は cookie 同送 (`credentials: 'include'`)
- ロール別画面遷移は AdminResource の `role` (`system_admin` / `general_admin`) を見て切り替える
