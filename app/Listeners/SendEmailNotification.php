<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\AdminUserUpdate;
use App\Mail\UserUpdated;

class SendEmailNotification
{
    /**
     * Handle the event.
     *
     * @param AdminUserUpdate $event
     * @return void
     */
    public function handle(AdminUserUpdate $event)
    {
        UserUpdated::sendMail([$event->user]);
    }
}
