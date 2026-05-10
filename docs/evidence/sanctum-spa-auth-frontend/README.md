# PR 3: Sanctum SPA 認証 (フロント) 動作確認

PR #75 で整備したバックエンド (Sanctum SPA + Multi-guard) を Next.js 側から消費する。User と Admin の 2 系統で、`/sanctum/csrf-cookie` → `/api/login` → `/api/me` → `/api/logout` を一通り通せることを確認する。

## 環境

- Laravel API: `http://localhost`
- Next.js dev: `http://localhost:3000` (Sail コンテナ内 `next dev -H 0.0.0.0 -p 3000`)
- DB: 既存の seed (`user@example.com`, `admin@example.com`、いずれもパスワード `password`)

## 構成

- 共通 axios クライアント `frontend/lib/api.ts`
  - `withCredentials: true` / `withXSRFToken: true` / `xsrfCookieName: 'XSRF-TOKEN'` / `xsrfHeaderName: 'X-XSRF-TOKEN'`
  - `ensureCsrfCookie()` で `/sanctum/csrf-cookie` を 1 度だけ発行
- 認証フック `frontend/lib/auth.ts`
  - `useUser()` / `useAdmin()` がそれぞれ `/api/me` / `/api/admin/me` を叩いて状態を返す
  - `loginUser` / `loginAdmin` / `logoutUser` / `logoutAdmin` を export
- フォーム共通コンポーネント `frontend/components/LoginForm.tsx`
  - email / password / submit、サーバ側 `message` をエラー表示
- 画面
  - `/login` : User ログイン → 成功で `/me`
  - `/admin/login` : Admin ログイン → 成功で `/admin/me`
  - `/me` : User ダッシュボード (id / name / email + ログアウト)
  - `/admin/me` : Admin ダッシュボード (id / name / email / role + ログアウト)
  - `/` : health 表示 + 2 つのログイン入口

## E2E ブラウザ確認

Chrome (Claude in Chrome) で以下を確認した。PNG はプロジェクト方針で割愛し、`read_page` の DOM スナップショットで代替する。

### 1. User ログイン → /me

```
form
 heading "User ログイン"
 label "Email" (textbox type=email)
 label "Password" (textbox type=password)
 button "Sign in"
```

`user@example.com` / `password` を入力 → 遷移先 `/me`:

```
main
 heading "User ダッシュボード"
 generic "name" / "Louvenia Zemlak"
 generic "email" / "user@example.com"
 button "ログアウト"
```

### 2. Admin ログイン → /admin/me

`admin@example.com` / `password` を入力 → 遷移先 `/admin/me`:

```
main
 heading "Admin ダッシュボード"
 generic "name" / "Sterling Goyette"
 generic "email" / "admin@example.com"
 generic "role" / "system_admin"
 button "ログアウト"
```

### 3. ログイン失敗

`user@example.com` / `wrong-password` を `/login` に投入:

```
form
 heading "User ログイン"
 textbox "user@example.com"
 textbox [value redacted]
 alert "These credentials do not match our records."
 button "Sign in"
```

サーバ側 (`AuthController::login` で `ValidationException`) のメッセージがそのまま画面に表示される。

### 4. クロスガード分離

User と Admin にそれぞれログインした状態で `/me` と `/admin/me` を行き来したところ、各画面が **対応する guard のデータをそれぞれ表示**することを確認した (web セッションと admin セッションが同時に存在しても、画面側のフックは自分用の guard だけを参照する)。

## 自動チェック

- `./vendor/bin/sail bash -c "cd frontend && npm run lint"` PASS (React 19 の `set-state-in-effect` 規則含む)
- `./vendor/bin/sail bash -c "cd frontend && npm run build"` PASS
- ルート: `/`, `/_not-found`, `/admin/login`, `/admin/me`, `/login`, `/me`

## 後続 PR への申し送り

- 本実装はまだログイン後ホームへの自動リダイレクトを行っていない (`/login` を直接踏むと既ログインでもログイン画面が出る)。オークション機能 (PR 6) を作る際にナビゲーションを整理する
- React 19 の `set-state-in-effect` 規則のため、`useEffect` 内で setState は cancel フラグ + Promise 経由で行う形に統一した
- axios の `withXSRFToken: true` と `withCredentials: true` を両方有効化することで CSRF cookie 同送が成立している
