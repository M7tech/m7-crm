<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use App\Services\TaskWorkflow;
use Illuminate\Http\RedirectResponse;

class TaskStatusController extends Controller
{
    public function update(UpdateTaskStatusRequest $request, int $task, TaskWorkflow $workflow): RedirectResponse
    {
        $taskModel = Task::query()->findOrFail($task);
        $workflow->setStatus($taskModel, (string) $request->validated('status'), $request->user());

        return back()->with('status', $request->validated('status') === 'completed' ? 'Task completed.' : 'Task reopened.');
    }
}
