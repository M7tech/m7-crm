<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TaskWorkflow
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor): Task {
            $task = Task::create([
                ...$this->normalized($data, $actor),
                'created_by_id' => $actor->id,
                'status' => 'pending',
            ]);
            $this->activity($task, $actor, 'created', 'Task created.');

            return $task;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor): Task {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $task->update($this->normalized($data, $actor));
            $this->activity($task, $actor, 'updated', 'Task details updated.');

            return $task;
        });
    }

    public function setStatus(Task $task, string $status, User $actor): Task
    {
        return DB::transaction(function () use ($task, $status, $actor): Task {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->status === $status) {
                return $task;
            }

            $previous = $task->status;
            $task->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
            [$type, $description] = match (true) {
                $status === 'completed' => ['completed', 'Task completed.'],
                $status === 'in_progress' => ['started', 'Task moved to in progress.'],
                $previous === 'completed' => ['reopened', 'Task reopened.'],
                default => ['status_changed', 'Task moved to to do.'],
            };
            TaskActivity::create([
                'task_id' => $task->id,
                'actor_id' => $actor->id,
                'type' => $type,
                'description' => $description,
                'metadata' => ['from' => $previous, 'to' => $status],
            ]);

            return $task;
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalized(array $data, User $actor): array
    {
        $timezone = $actor->tenant?->timezone ?? 'Asia/Baghdad';
        $data['due_at'] = CarbonImmutable::parse((string) $data['due_at'], $timezone)->utc();
        $data['reminder_at'] = filled($data['reminder_at'] ?? null)
            ? CarbonImmutable::parse((string) $data['reminder_at'], $timezone)->utc()
            : null;
        $data['reminder_sent_at'] = null;

        if (filled($data['contact_id'] ?? null) && blank($data['company_id'] ?? null)) {
            $data['company_id'] = Contact::query()->whereKey((int) $data['contact_id'])->value('company_id');
        }

        return $data;
    }

    private function activity(Task $task, User $actor, string $type, string $description): void
    {
        TaskActivity::create([
            'task_id' => $task->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
