<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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

    public function toMail(object $notifiable): MailMessage
    {
        $expiresIn = (int) config('auth.verification.expire', 60);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expiresIn),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->line('Gracias por registrarte. Confirma tu correo electrónico para activar tu cuenta.')
            ->action('Verificar correo electrónico', $url)
            ->line('Este enlace expira en '.$expiresIn.' minutos.')
            ->line('Si no creaste una cuenta, puedes ignorar este mensaje.');
    }
}
