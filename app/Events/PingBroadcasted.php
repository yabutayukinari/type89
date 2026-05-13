<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WebSocket（Reverb）配信経路のスモークテスト用イベント。
 *
 * 本番のビジネスロジックでは使用しない。`POST /api/broadcast-test`
 * （{@see \App\Http\Controllers\Api\BroadcastTestController}）から発火され、
 * 公開チャンネル `public.ping` にイベント名 `ping` で配信される。
 *
 * 用途は「Laravel → Reverb → ブラウザ」の疎通確認のみ。
 * 障害時に配信経路（インフラ・WebSocket）側の問題か、業務ロジック側の問題かを
 * 切り分けるための診断用エンドポイントとして残している。
 *
 * 削除する場合は以下も併せて削除すること:
 *   - {@see \App\Http\Controllers\Api\BroadcastTestController}
 *   - routes/api.php の `api.broadcast-test` ルート
 *   - routes/channels.php の `public.ping` チャンネル登録
 *   - tests/Unit/Events/PingBroadcastedTest.php
 *   - tests/Feature/Api/BroadcastTestControllerTest.php
 */
class PingBroadcasted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 配信ペイロードを受け取って保持する。
     *
     * `$message` と `$emittedAt` はそのまま {@see self::broadcastWith()} を
     * 通じてブラウザに送られる。
     *
     * @param string $message フロントに表示・ログ出力するための任意のメッセージ文字列
     * @param string $emittedAt サーバ側でイベントを生成した時刻（ISO 8601 形式を想定）
     */
    public function __construct(
        public string $message,
        public string $emittedAt,
    ) {
    }

    /**
     * 配信先チャンネルを返す。
     *
     * 認証不要の公開チャンネル `public.ping` に流す。スモークテスト用途のため
     * PrivateChannel ではなく Channel（公開）を使用している。
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('public.ping')];
    }

    /**
     * クライアント側で購読するイベント名を返す。
     *
     * 既定では完全修飾クラス名（`App\Events\PingBroadcasted`）になるが、
     * フロント（Echo）側で `.listen('.ping', ...)` と短い名前で受信できるよう
     * 短いエイリアス `ping` を返す。
     */
    public function broadcastAs(): string
    {
        return 'ping';
    }

    /**
     * ブラウザに送る JSON ペイロードを返す。
     *
     * public プロパティをそのまま送らず、キー名をスネークケースに整形する
     * （フロント側 JS の命名規約に合わせるため）。
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'emitted_at' => $this->emittedAt,
        ];
    }
}
