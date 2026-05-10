# PR 1: Next.js セットアップ動作確認

`frontend/` に Next.js (TypeScript + App Router + Tailwind CSS) を立ち上げ、Laravel API の `/api/health` を fetch して画面に表示できることを確認した。

## 環境

- Laravel: Sail コンテナ (`http://localhost`)
- Next.js: Sail コンテナ内 dev server (`http://localhost:3000`、`next dev -H 0.0.0.0 -p 3000`)
- ブラウザ: Chrome (Claude in Chrome 経由で navigate)

## 確認内容

### 1. Laravel API の health JSON

```
$ curl -sf -w "\nHTTP %{http_code}\n" http://localhost/api/health
{"status":"ok"}
HTTP 200
```

CORS ヘッダも `api/*` 配下のため自動で付与される:

```
$ curl -sf -H "Origin: http://localhost:3000" -I http://localhost/api/health
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: *
```

### 2. Next.js のビルドと Lint

```
$ ./vendor/bin/sail bash -c "cd frontend && npm run lint && npm run build"
> frontend@0.1.0 lint
> eslint
(警告なし)

> frontend@0.1.0 build
> next build

▲ Next.js 16.2.6 (Turbopack)
✓ Compiled successfully in 1088ms
✓ Generating static pages using 5 workers (4/4) in 209ms

Route (app)
┌ ○ /
└ ○ /_not-found
```

### 3. ブラウザでの表示確認

`http://localhost:3000/` を Chrome で開いた DOM:

```
main
 heading "type89"
 region
  heading "Laravel API health"
  generic "status:"
  (ok)
```

`status: ok` がエメラルドグリーンで表示されることを目視確認した
（Claude in Chrome の `read_page` および `screenshot` で確認、PNG 保存は環境制約によりスキップ）。

エラーケース (`Failed to fetch`) も初回確認時に再現済み。これは fetch 先を `/`
（CORS 対象外）にしていたためで、`/api/health` に切り替えて解消。

## ファイル構成

- `frontend/` ディレクトリ (Next.js 16.2.6 + React 19 + Tailwind 4)
- `routes/api.php` に `/api/health` を追加
- `frontend/app/page.tsx` で `${NEXT_PUBLIC_API_URL}/api/health` を fetch
- `frontend/.env.example` に `NEXT_PUBLIC_API_URL` を例示
- `docker-compose.yml` で port 3000 を laravel.test に公開（Vite ポートは削除）
- `.github/workflows/ci.yml` に `frontend` ジョブ (npm ci + lint + build) を追加

## 後続 PR への申し送り

- 本番ビルドの fetch URL は環境変数で切り替える前提（PR 2/3 で本番想定の認証フロー
  を組む際に詰める）
- スクリーンショット保存手段は環境制約で MD のみとする方針 (PR 3/5/6 も同じ)
