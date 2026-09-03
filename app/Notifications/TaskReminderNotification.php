<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $timezone = $this->task->tenant->timezone;

        return (new MailMessage)
            ->subject('Task reminder: '.$this->task->title)
            ->greeting('Sales follow-up reminder')
            ->line($this->task->title.' is due '.$this->task->due_at->timezone($timezone)->toDayDateTimeString().'.')
            ->action('Open task', route('tasks.show', $this->task))
            ->line('This reminder was scheduled in your CRM workspace.');
    }
}
