<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Queued password reset link (custom branded mailable). */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): PasswordResetMail
    {
        /** @var User $notifiable */
        return (new PasswordResetMail($notifiable, $this->token))
            ->to($notifiable->email);
    }
}
