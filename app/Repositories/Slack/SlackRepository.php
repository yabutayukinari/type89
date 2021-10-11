<?php declare(strict_types=1);

namespace App\Repositories\Slack;

use Illuminate\Notifications\Notifiable;

class SlackRepository implements SlackRepositoryInterface
{
    use Notifiable;

    /**
     * @return string
     */
    public function routeNotificationForSlack() :string
    {
        return env('SLACK_WEBHOOK_URL');
    }
}
