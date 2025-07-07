<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends Notification
{
    public $token;
    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));

        return (new MailMessage)
            ->subject('Réinitialisation de mot de passe')
            ->line('Vous avez demandé une réinitialisation de mot de passe.')
            ->action('Réinitialiser le mot de passe', $url)
            ->line('Ce lien expirera dans 5 minutes.');
    }
}
