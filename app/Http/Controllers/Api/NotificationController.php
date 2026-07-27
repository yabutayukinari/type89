<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知を新しい順に返す。未読件数も添える。
     */
    public function index(): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $notifications = $user->notifications()->latest()->get();

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }

    /**
     * 未読の通知をすべて既読にする。
     */
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $user->unreadNotifications->markAsRead();

        return response()->json(['status' => 'ok']);
    }
}
