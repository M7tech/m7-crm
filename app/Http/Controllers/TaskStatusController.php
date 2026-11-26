<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use App\Services\TaskWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TaskStatusController extends Controller
{
    public function update(UpdateTaskStatusRequest $request, int $task, TaskWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $taskModel = Task::query()->findOrFail($task);
        $status = (string) $request->validated('status');
        $taskModel = $workflow->setStatus($taskModel, $status, $request->user());
        $message = match ($status) {
            'completed' => __('Task completed.'),
            'in_progress' => __('Task moved to in progress.'),
            default => __('Task moved to to do.'),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $taskModel->id,
                'status' => $taskModel->status,
                'status_label' => Task::statusLabel($taskModel->status),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }
}
