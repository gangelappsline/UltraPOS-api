<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Queued email verification message (custom branded mailable). */
class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): VerifyEmailMail
    {
        /** @var User $notifiable */
        return (new VerifyEmailMail($notifiable))->to($notifiable->email);
    }
}
