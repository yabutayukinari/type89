<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 入札が成立したことを表す Event。
 *
 * ShouldBroadcast を実装しているため、サーバ内 Listener への配送に加えて
 * Reverb (WebSocket) 経由でブラウザにも push される。
 *
 * Event クラスは「起きたことを表すデータの入れ物 (DTO)」であり、
 * 発生源 (BidController) と受け取り側 (Broadcaster / Listener) を疎結合にする。
 */
class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event が運ぶデータをコンストラクタで受け取り、そのままプロパティに保持する。
     *
     * SerializesModels trait のおかげで、キュー経由で渡される際は
     * Bid モデルが ID にシリアライズされ、受信側で自動的に再フェッチされる。
     */
    public function __construct(public Bid $bid)
    {
    }

    /**
     * このイベントを流すブロードキャストチャンネルを返す。
     *
     * "宛先" にあたる。auction.{id} という公開チャンネルを使用しており、
     * 同じオークションを観戦している全クライアントが受信できる。
     * 認証が必要なら PrivateChannel / PresenceChannel を使い、
     * routes/channels.php で認可ロジックを書く。
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("auction.{$this->bid->auction_id}")];
    }

    /**
     * フロントエンドが listen するイベント名 ("件名" にあたる)。
     *
     * 未定義だと FQCN (App\Events\BidPlaced) で配信されてしまうため、
     * 短い識別子を明示する。Laravel Echo 側では先頭にドットを付けて
     * .listen('.bid.placed', ...) と購読する (ドット無しだとクラス名扱いになる)。
     */
    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    /**
     * ブラウザに送る JSON ペイロード ("本文" にあたる)。
     *
     * 未定義だと Event の public プロパティが丸ごと JSON 化されてしまい、
     * 不要な内部情報まで漏れたりスキーマ変更でフロントが壊れたりするため、
     * 公開してよい項目だけを明示的に組み立てる。
     * loadMissing でリレーションをまとめて読み込み、N+1 を防いでいる。
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $bid = $this->bid->loadMissing('bidder', 'auction');

        return [
            'bid' => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'created_at' => $bid->created_at->toIso8601String(),
                'bidder' => [
                    'id' => $bid->bidder->id,
                    'name' => $bid->bidder->name,
                ],
            ],
            'auction' => [
                'id' => $bid->auction->id,
                'current_price' => $bid->auction->current_price,
                'min_next_bid' => $bid->auction->minNextBid(),
                'current_winner' => [
                    'id' => $bid->bidder->id,
                    'name' => $bid->bidder->name,
                ],
            ],
        ];
    }
}
