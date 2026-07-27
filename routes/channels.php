<?php declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('public.ping', static fn () => true);

Broadcast::channel('user.{userId}', static fn (User $user, int $userId): bool => $user->id === $userId);

// オークションは観戦自体は誰でも可能 (パブリックチャネル)。入札は API 側で auth + 認可。
Broadcast::channel('auction.{auctionId}', static fn () => true);

// 観戦人数を数えるための Presence チャンネル。認証済みユーザーのみ join でき、
// 返した配列 (id / name) が「今このオークションを見ているメンバー」として全員に共有される。
// 匿名の閲覧者はここには現れず、人数にも含まれない。
Broadcast::channel(
    'auction-presence.{auctionId}',
    static fn (User $user): array => ['id' => $user->id, 'name' => $user->name],
);
