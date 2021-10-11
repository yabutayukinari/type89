<?php declare(strict_types=1);

namespace App\Repositories\Slack;

interface SlackRepositoryInterface
{
    public function routeNotificationForSlack(): string;
}
