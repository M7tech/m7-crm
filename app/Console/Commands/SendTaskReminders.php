<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskReminderNotification;
use App\Support\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Queue due task reminder notifications';

    public function handle(CurrentTenant $currentTenant): int
    {
        $currentTenant->allowGlobalAccess();

        $taskIds = Task::query()
            ->where('status', 'pending')
            ->whereNotNull('reminder_at')
            ->whereNull('reminder_sent_at')
            ->where('reminder_at', '<=', now())
            ->whereNotNull('assigned_to_id')
            ->pluck('id');

        foreach ($taskIds as $taskId) {
            $this->queueReminder((int) $taskId);
        }

        return self::SUCCESS;
    }

    private function queueReminder(int $taskId): void
    {
        DB::transaction(function () use ($taskId): void {
            $task = Task::query()->with('assignedTo')->lockForUpdate()->find($taskId);

            if (! $task || $task->status !== 'pending' || $task->reminder_sent_at !== null || $task->reminder_at?->isFuture()) {
                return;
            }

            $task->update(['reminder_sent_at' => now()]);
            $task->assignedTo?->notify(new TaskReminderNotification($task));
        });
    }
}
