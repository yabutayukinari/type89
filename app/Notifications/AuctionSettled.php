<?php declare(strict_types=1);

namespace App\Notifications;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * オークションの落札確定を出品者・落札者へ知らせる Notification。
 *
 * 1 つのクラスで両者に配信し、受信者 ($notifiable) に応じて文面を出し分ける。
 * via に database と broadcast を指定しており、
 *   - database: 後から /me で参照できる履歴として残す
 *   - broadcast: Reverb 経由で本人の private チャンネルへリアルタイム push
 * の二段構えになっている。broadcast 先チャンネルは User::receivesBroadcastNotificationsOn()
 * で既存の user.{id} に合わせている。
 */
class AuctionSettled extends Notification
{
    public function __construct(public Auction $auction)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * DB に保存される表現。broadcast のペイロードもこれを再利用する。
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isWinner = $notifiable instanceof User
            && $this->auction->current_winner_user_id === $notifiable->id;

        return [
            'auction_id' => $this->auction->id,
            'title' => $this->auction->title,
            'role' => $isWinner ? 'winner' : 'seller',
            'has_winner' => $this->auction->hasWinner(),
            'final_price' => $this->auction->current_price,
            'message' => $this->buildMessage($isWinner),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    private function buildMessage(bool $isWinner): string
    {
        $price = number_format($this->auction->current_price);

        if ($isWinner) {
            return "「{$this->auction->title}」を {$price} 円で落札しました";
        }

        if ($this->auction->hasWinner()) {
            return "出品した「{$this->auction->title}」が {$price} 円で落札されました";
        }

        return "出品した「{$this->auction->title}」は入札なしで終了しました";
    }
}
