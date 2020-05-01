<?php

namespace OpenDominion\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use OpenDominion\Models\User;

class RegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @param User $user
     * @return array
     */
    public function via(User $user): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param User $user
     * @return MailMessage
     */
    public function toMail(User $user): MailMessage
    {
        return (new MailMessage)
            ->replyTo('info@odarena.com', 'ODARENA')
            ->subject('ODARENA Registration')
            ->greeting('ODARENA Registration')
            ->line('Hi, ' . $user->display_name . '!')
            ->line('You are receiving this email because someone using this email address recently registered for the free online strategy-war game ODARENA. If you did not register for ODARENA, don\'t worry, the person using this email address will need to click the activation link below to continue playing.')
            ->line('If you did indeed register for ODARENA, then welcome to the game! Please click the activation link below because you *will need to click it*, and there is no way to activate your account other than contacting the owner if you delete this message.')
            ->action('Activate your account', route('auth.activate', $user->activation_code))
            ->line('You can find ODARENA at: ' . route('home'))
            ->line('Thank you for playing, and have fun!')
            ->salutation('-ODARENA');
    }
}
