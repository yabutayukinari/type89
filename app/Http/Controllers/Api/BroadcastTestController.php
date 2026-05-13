<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\PingBroadcasted;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * WebSocket（Reverb）配信経路の疎通確認エンドポイント。
 *
 * `POST /api/broadcast-test` にアクセスすると {@see PingBroadcasted} を発火し、
 * 公開チャンネル `public.ping` にイベント名 `ping` でブロードキャストする。
 * レスポンス JSON には配信先チャンネル・イベント名・ペイロードを返すため、
 * 「サーバ側が正常に発火したか」と「ブラウザ側が受信できたか」を別々に確認できる。
 *
 * 本番のビジネスロジックでは利用しない。障害切り分け（インフラ／WebSocket 側か、
 * 業務ロジック側かの判定）専用の診断用ツールとして残している。
 */
class BroadcastTestController extends Controller
{
    /**
     * 疎通確認イベントを発火し、配信内容を JSON で返す。
     *
     * `pong from Laravel` という固定メッセージと、サーバの現在時刻（ISO 8601）を
     * ペイロードに乗せて {@see PingBroadcasted} を発火する。
     * `broadcast()->toOthers()` を使っているため、同一接続のクライアント
     * （X-Socket-Id を送ってきたリクエスト元）には配信されない点に注意。
     *
     * レスポンスにはサーバ側で組み立てた配信内容（チャンネル名・イベント名・
     * ペイロード）をそのまま返すため、ブラウザ側で「受信できた値」と
     * 「サーバが送ったはずの値」を突き合わせて疎通を判定できる。
     */
    public function __invoke(): JsonResponse
    {
        $event = new PingBroadcasted(
            message: 'pong from Laravel',
            emittedAt: Carbon::now()->toIso8601String(),
        );

        broadcast($event)->toOthers();

        return response()->json([
            'broadcast' => 'public.ping',
            'event' => 'ping',
            'payload' => $event->broadcastWith(),
        ]);
    }
}
