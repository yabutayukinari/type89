<?php declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('public.ping', static fn () => true);

Broadcast::channel('user.{userId}', static fn (User $user, int $userId): bool => $user->id === $userId);
