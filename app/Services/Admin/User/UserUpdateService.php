<?php declare(strict_types=1);

namespace App\Services\Admin\User;

use App\Events\AdminUserUpdate;
use App\Models\User;
use App\Notifications\UpdateUserSlack;
use App\Repositories\Slack\SlackRepository as SlackPepo;

class UserUpdateService
{
    private SlackPepo $slackHook;
    private UpdateUserSlack $slack;

    public function __construct(SlackPepo $slackHook, UpdateUserSlack $slack)
    {
        $this->slackHook = $slackHook;
        $this->slack = $slack;
    }

    /**
     * ユーザー更新処理
     *
     * @param User $user
     * @param array $params
     */
    public function execute(User $user, array $params): void
    {
        $user->update($params);
        AdminUserUpdate::dispatch($user);
        $this->slackHook->notify($this->slack);
    }
}
