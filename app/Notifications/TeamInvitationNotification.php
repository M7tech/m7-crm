<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $encryptedToken;

    public function __construct(public Invitation $invitation, string $token)
    {
        $this->encryptedToken = Crypt::encryptString($token);
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $token = Crypt::decryptString($this->encryptedToken);

        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('You are invited!')
            ->line('You have been invited to join '.$this->invitation->tenant->name.' as '.$this->invitation->role->label().'.')
            ->action('Accept invitation', route('invitations.accept.show', ['token' => $token]))
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.');
    }
}
