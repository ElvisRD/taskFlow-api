<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject('Te invitaron a '.$workspace->name)
            ->line(($inviter?->name ?? 'Un administrador').' te invitó a unirte al workspace "'.$workspace->name.'" como '.$this->invitation->role->label().'.')
            ->action('Aceptar invitación', $this->invitationUrl())
            ->line('Este enlace expira el '.$this->invitation->expires_at->format('d/m/Y H:i').'.')
            ->line('Si no esperabas esta invitación, puedes ignorar este mensaje.');
    }

    private function invitationUrl(): string
    {
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $frontend.'/invitations/'.$this->token;
    }
}
