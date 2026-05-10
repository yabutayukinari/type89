<?php declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('public.ping', static fn () => true);

Broadcast::channel('user.{userId}', static fn (User $user, int $userId): bool => $user->id === $userId);

// オークションは観戦自体は誰でも可能 (パブリックチャネル)。入札は API 側で auth + 認可。
Broadcast::channel('auction.{auctionId}', static fn () => true);
