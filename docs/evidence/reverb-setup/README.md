# PR 4: Laravel Reverb + Echo 動作確認

オークション機能 (PR 5/6) の Broadcast を担うため、Laravel Reverb (WebSocket) と Next.js 側の Laravel Echo を立ち上げ、`public.ping` チャネルでの broadcast 往復を確認する。

## 構成

### バックエンド
- `composer require laravel/reverb` + `php artisan reverb:install`
- `app/Providers/BroadcastServiceProvider.php` を新規作成 (Sanctum SPA セッションで `/broadcasting/auth` を回す)
- `routes/channels.php` に
  - `public.ping` (パブリック、誰でも購読可)
  - `user.{userId}` (private、`User` のみ購読可)
- `app/Events/PingBroadcasted.php` (`ShouldBroadcast`) — チャネル `public.ping`、イベント名 `ping`
- `app/Http/Controllers/Api/BroadcastTestController.php` — `POST /api/broadcast-test` で Ping を発火
- `config/broadcasting.php` に `reverb` connection を追加 (Reverb 1.x のため自動生成されないので手動)
- `config/app.php` の providers 配列で `BroadcastServiceProvider` を有効化 (reverb:install が解放)
- `docker-compose.yml` で laravel.test サービスから 8080 を外部公開

### Frontend
- `frontend/lib/echo.ts` で `Echo`/`Pusher` のシングルトンを生成 (broadcaster: `'reverb'`)
- `frontend/app/broadcast-test/page.tsx` で `public.ping` を購読し、ボタン押下で `/api/broadcast-test` を叩いて broadcast を発火
- `frontend/.env.example` に `NEXT_PUBLIC_REVERB_*` を追加 (Vite 用は撤去済み)

## 環境変数 (.env)

```
BROADCAST_DRIVER=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
NEXT_PUBLIC_REVERB_APP_KEY="${REVERB_APP_KEY}"
NEXT_PUBLIC_REVERB_HOST="${REVERB_HOST}"
NEXT_PUBLIC_REVERB_PORT="${REVERB_PORT}"
NEXT_PUBLIC_REVERB_SCHEME="${REVERB_SCHEME}"
```

`frontend/.env.local` (git 管理外) には `NEXT_PUBLIC_REVERB_APP_KEY` を実値で設定する想定。

## 動作確認の手順

1. `./vendor/bin/sail up -d`
2. `./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port=8080`
3. `./vendor/bin/sail bash -c "cd frontend && npm run dev"`
4. ブラウザで `http://localhost:3000/broadcast-test`

## 動作確認ログ

接続直後の DOM (Reverb への WebSocket 接続が成立):

```
heading "Broadcast 動作確認"
region "Reverb: connected"
button "ping を broadcast"
list ("受信した ping はまだありません")
```

`ping を broadcast` ボタン押下後の DOM (broadcast 受信):

```
heading "Broadcast 動作確認"
region "Reverb: connected"
button "ping を broadcast"
list
  listitem
    generic "pong from Laravel"
    generic "emitted: 2026-05-11T01:19:25+09:00"
    generic "received: 2026-05-10T16:19:25.056Z"
```

`POST /api/broadcast-test` → Laravel が `PingBroadcasted` イベントを発火 → Reverb 経由で WebSocket クライアント (Echo) に届く、までの一連が成立。

`emitted` (Laravel 側) と `received` (ブラウザ) のタイムスタンプが同秒 (差は WebSocket レイテンシ程度) になっており、リアルタイム性も確認できた。

## 自動チェック

- `make build` (csf/cs/sa/md) PASS
- `make test` (18 tests, 41 assertions) PASS
- `npm run lint`, `npm run build` (Next.js 16) PASS
- 新ルート: `/broadcast-test`

## 後続 PR

- PR 5: オークション API + Broadcast (auctions / bids、`AuctionUpdated`, `BidPlaced` Event、`auction.{id}` チャネル)
- PR 6: オークション UI (一覧 / 詳細 / 入札、Echo で価格更新)
