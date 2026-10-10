<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BusinessRegistered;
use App\Mail\WelcomeMail;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/** Queued: sends the welcome email + email verification on registration. */
class SendWelcomeToNewBusiness implements ShouldQueue
{
    public function handle(BusinessRegistered $event): void
    {
        Mail::to($event->owner->email)
            ->queue(new WelcomeMail($event->owner, $event->business));

        $event->owner->notify(new VerifyEmailNotification());
    }
}
