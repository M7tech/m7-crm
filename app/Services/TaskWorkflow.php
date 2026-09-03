<?php

namespace App\Services;

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

            $task->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
            $this->activity(
                $task,
                $actor,
                $status === 'completed' ? 'completed' : 'reopened',
                $status === 'completed' ? 'Task completed.' : 'Task reopened.',
            );

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
