# PR 6: オークション UI (Next.js + Echo) 動作確認

PR 5 で整備した API + Broadcast を Next.js から消費し、出品 / 一覧 / 詳細 / 入札 / リアルタイム価格更新までを画面で完結させる。

## 画面

- `/auctions` — 一覧。各オークションのタイトル / 現在価格 / status / 出品者
- `/auctions/new` — 出品フォーム (タイトル / 説明 / 開始価格 / 入札単位 / 開始・終了日時)
- `/auctions/[id]` — 詳細 (出品情報 + 入札フォーム + リアルタイム入札ログ)
- ホーム (`/`) のナビに「オークション一覧」を追加

## ライブラリ

- API クライアント `frontend/lib/auctions.ts`: `fetchAuctions` / `fetchAuction` / `createAuction` / `placeBid`
- Echo は既存の `frontend/lib/echo.ts` を流用 (broadcaster: \`reverb\`、channel `auction.{id}`)

## 動作確認 (Chrome)

事前に `php artisan reverb:start` と `npm run dev` を起動。`migrate:fresh` 後に seller / bidder / 1 件のオークション (5000 円 / 入札単位 500 円) を seed。

### 1. 一覧

```
heading "オークション一覧"
link "出品する" → /auctions/new
list
  listitem
    link → /auctions/1
      "ヴィンテージ腕時計"
      "現在価格" "5,000" 円
    "出品者: Seller Sato"
```

### 2. 詳細 (未ログイン or seller としてアクセス)

未ログインなら「入札にはログインが必要です」、seller としてアクセスすると「自分の出品物には入札できません」と表示され、入札フォームは出ない。

### 3. bidder としてログインして入札

`/login` で `bidder@example.com` / `password` でログイン後 `/auctions/1` を再表示すると、入札フォームが現れ、入力欄は \`min_next_bid\` (= 5000) で初期化される。

「入札する」を押下 → `POST /api/auctions/1/bids` が発火 → `BidPlaced` event が `auction.1` チャネルに broadcast され、自分自身も受信:

```
generic "現在価格" "5,000 円"      # 初回入札 (額が starting_price と同じ) のため見た目は変化しない
generic "最低次回入札" "5,500 円"  # 5000 + 500
generic "最高入札者" "Bidder Brown"
listitem "Bidder Brown が 5,000 円で入札"
```

続けて 6000 円で入札:

```
generic "現在価格" "6,000 円"      # ← 5000 → 6000 にリアルタイム更新
generic "最低次回入札" "6,500 円"
generic "最高入札者" "Bidder Brown"
list (新しい順)
  listitem "Bidder Brown が 6,000 円で入札"
  listitem "Bidder Brown が 5,000 円で入札"
```

入札額の input は `min_next_bid` (= 6500) で自動再初期化される。

### 4. クロスガード分離 / アクセス制御

- 未ログインで `/auctions/new` を踏むと `/login` にリダイレクト
- 未ログインの詳細表示でも閲覧は可能 (channel が公開のため observation だけはできる)
- seller としてログインすると自分の出品には入札できない (UI レベルでフォーム非表示、API レベルでもガード済み — PR 5 のテスト参照)

## 自動チェック

- `npm run lint` (Next.js 16 / ESLint 9) PASS
- `npm run build` PASS
  - 新ルート: `/auctions`, `/auctions/[id]` (ƒ Dynamic), `/auctions/new`
- `make build` (Laravel 側、ファイル追加なし) PASS
- `make test` (29 件、PR 5 由来含む) PASS

## 既知の挙動

- `BidPlaced` の broadcast は `QUEUE_CONNECTION=sync` のため Reverb への HTTP publish が同期実行される。**Reverb サーバが落ちていると入札 API が 500 を返す** が、bid 自体は DB に保存される (cf. PR 5 README)
- 開発時は Reverb と Next.js dev の両方を起動する必要がある:
  ```
  ./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port=8080
  ./vendor/bin/sail bash -c "cd frontend && npm run dev"
  ```

## 完成したスタック

| レイヤ | 技術 |
|---|---|
| バックエンド | Laravel 13 + PHP 8.4 + Sanctum 4 |
| フロント | Next.js 16 + React 19 + TypeScript + Tailwind 4 |
| 認証 | Sanctum SPA 認証 (Multi-guard: web / admin) |
| WebSocket | Laravel Reverb 1.10 (Pusher 互換) |
| クライアント受信 | laravel-echo + pusher-js |
| 開発環境 | Laravel Sail (Docker) |
