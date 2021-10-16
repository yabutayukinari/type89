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

    public function execute(User $user, array $params)
    {
        $user->update($params);
        AdminUserUpdate::dispatch($user);
        $this->slackHook->notify($this->slack);
    }
}
