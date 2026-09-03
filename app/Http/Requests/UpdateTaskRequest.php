<?php

namespace App\Http\Requests;

use App\Models\Task;

class UpdateTaskRequest extends StoreTaskRequest
{
    public function authorize(): bool
    {
        $task = Task::query()->find((int) $this->route('task'));

        return $task !== null && ($this->user()?->can('update', $task) ?? false);
    }
}
