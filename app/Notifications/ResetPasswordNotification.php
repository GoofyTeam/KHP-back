<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via()
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = config('app.frontend_url')
             . '/reset-password?token=' . $this->token
             . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->line("Vous recevez cet e-mail car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.")
            ->action('Réinitialiser le mot de passe', $url)
            ->line("Si vous n'avez pas demandé cette réinitialisation, aucune autre action n'est requise.");
    }
}
