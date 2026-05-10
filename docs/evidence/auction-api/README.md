# PR 5: オークション API + Broadcast 動作確認

英国式オークションの中核 (出品 / 入札 / 価格更新の broadcast) を整備する。本 PR は API + Broadcast までで、UI は PR 6 で被せる。

## スキーマ

- `auctions` (id, seller_user_id, title, description, starting_price, bid_increment, current_price, current_winner_user_id, starts_at, ends_at)
- `bids` (id, auction_id, user_id, amount)

`current_price` と `current_winner_user_id` は入札時に正規化キャッシュとして更新する。`status` は `starts_at` と `ends_at` から計算 (pending / active / ended)。

## API

```
GET  /api/auctions               — 一覧 (誰でも)
GET  /api/auctions/{id}          — 詳細
POST /api/auctions               — 出品 (auth:web、StoreAuctionRequest でバリデーション)
POST /api/auctions/{id}/bids     — 入札 (auth:web、PlaceBidRequest)
```

## Broadcast

- チャネル `auction.{id}` (公開、誰でも観戦可)
- イベント `bid.placed` を `App\Events\BidPlaced` で発火
- payload は `bid` (id / amount / bidder / created_at) と `auction` (id / current_price / min_next_bid / current_winner)

## 入札のドメインルール

`BidController::store` の DB トランザクション内で次を検証 (排他は `lockForUpdate`):

1. `status === 'active'` (期間内である)
2. 出品者と入札者が異なる (`seller_user_id !== bidder->id`)
3. 入札額が `min_next_bid` 以上 (初回は `starting_price`、以降は `current_price + bid_increment`)

違反時は `ValidationException` (422、`amount` フィールドにエラー)。

## 自動テスト

`tests/Feature/Api/AuctionTest.php` (5 件) と `tests/Feature/Api/BidTest.php` (6 件):

- 一覧 / 詳細 / 出品成功 / 認証必須 / `ends_at` バリデーション
- 入札成功 + `BidPlaced` 発火 (Event::fake) / 増額未達 / 自分の出品物 / 終了済み / 開始前 / 認証必須

```
$ ./vendor/bin/sail composer test
PHPUnit 12.5.24
.............................                                     29 / 29 (100%)
OK (29 tests, 71 assertions)
```

## E2E (curl)

JST 起動 (`config/app.timezone = Asia/Tokyo`) のため、入力日時もローカルタイムゾーン付きで送る。

```
== Create auction ==
{"data":{"id":1,...,"starting_price":1000,"current_price":1000,"min_next_bid":1000,"status":"active"}}

== Place bid (1200, valid) ==
{"data":{"id":3,"auction_id":1,"amount":1200,"bidder":{"id":2,"name":"..."},"created_at":"2026-05-11T01:28:34+09:00"}}

== Show auction ==
{"data":{"id":1,...,"current_price":1200,"min_next_bid":1300,"current_winner":{"id":2,"name":"..."},"status":"active"}}

== Too-low bid (1100, must be 1200) ==
{"message":"最低入札額は 1200 です","errors":{"amount":["最低入札額は 1200 です"]}}

== Bid on own auction (seller) ==
{"message":"自分の出品物には入札できません","errors":{"amount":["自分の出品物には入札できません"]}}
```

`current_price` がトランザクション内で 1100 → 1200 に更新され、`min_next_bid` も 1300 に追従していることを確認。

## 既知の挙動

- `App\Events\BidPlaced` は `ShouldBroadcast` (キュー経由) を使うが、開発環境は `QUEUE_CONNECTION=sync` のためインライン実行される。**Reverb サーバが起動していない状態で入札すると、DB 書き込みは成功するが broadcast 発火で 500 エラー** になる。本番投入前に `QUEUE_CONNECTION=redis` 等に切り替え、broadcast は async 化する想定。
- `auction.{id}` チャネルは公開設定。私情報や入札の上書きはしないため、観戦自体に認証は不要とした。

## 後続 PR

- PR 6: Next.js でオークション一覧 / 詳細 / 出品 / 入札 UI を実装し、Echo で `bid.placed` を受けてリアルタイムに価格更新する。動作確認時は事前に `php artisan reverb:start` を起動する。
