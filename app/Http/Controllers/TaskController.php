<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflow;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): View
    {
        $this->authorize('viewAny', Task::class);
        $timezone = $currentTenant->tenant()?->timezone ?? 'Asia/Baghdad';
        $startOfToday = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $endOfToday = CarbonImmutable::now($timezone)->endOfDay()->utc();
        $visible = $this->visibleQuery($request->user());
        $filter = in_array($request->query('filter'), ['pending', 'today', 'overdue', 'completed'], true)
            ? (string) $request->query('filter')
            : 'pending';

        $tasks = (clone $visible)
            ->with(['lead.company', 'assignedTo'])
            ->when($filter === 'pending', fn (Builder $query) => $query->where('status', 'pending'))
            ->when($filter === 'today', fn (Builder $query) => $query->where('status', 'pending')->whereBetween('due_at', [$startOfToday, $endOfToday]))
            ->when($filter === 'overdue', fn (Builder $query) => $query->where('status', 'pending')->where('due_at', '<', now()))
            ->when($filter === 'completed', fn (Builder $query) => $query->where('status', 'completed'))
            ->orderByRaw("case when priority = 'urgent' then 1 when priority = 'high' then 2 when priority = 'normal' then 3 else 4 end")
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filter' => $filter,
            'timezone' => $timezone,
            'counts' => [
                'pending' => (clone $visible)->where('status', 'pending')->count(),
                'today' => (clone $visible)->where('status', 'pending')->whereBetween('due_at', [$startOfToday, $endOfToday])->count(),
                'overdue' => (clone $visible)->where('status', 'pending')->where('due_at', '<', now())->count(),
                'completed' => (clone $visible)->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function create(Request $request, CurrentTenant $currentTenant): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.create', [
            ...$this->formData($request->user()),
            'selectedLeadId' => $request->integer('lead') ?: null,
            'timezone' => $currentTenant->tenant()?->timezone ?? 'Asia/Baghdad',
        ]);
    }

    public function store(StoreTaskRequest $request, TaskWorkflow $workflow): RedirectResponse
    {
        $task = $workflow->create($request->validated(), $request->user());

        return to_route('tasks.show', $task)->with('status', 'Task created successfully.');
    }

    public function show(int $task, CurrentTenant $currentTenant): View
    {
        $taskModel = Task::query()->with(['lead.company', 'assignedTo', 'createdBy', 'activities.actor'])->findOrFail($task);
        $this->authorize('view', $taskModel);

        return view('tasks.show', [
            'task' => $taskModel,
            'timezone' => $currentTenant->tenant()?->timezone ?? 'Asia/Baghdad',
        ]);
    }

    public function edit(Request $request, int $task, CurrentTenant $currentTenant): View
    {
        $taskModel = Task::query()->findOrFail($task);
        $this->authorize('update', $taskModel);

        return view('tasks.edit', [
            'task' => $taskModel,
            ...$this->formData($request->user()),
            'selectedLeadId' => null,
            'timezone' => $currentTenant->tenant()?->timezone ?? 'Asia/Baghdad',
        ]);
    }

    public function update(UpdateTaskRequest $request, int $task, TaskWorkflow $workflow): RedirectResponse
    {
        $taskModel = Task::query()->findOrFail($task);
        $workflow->update($taskModel, $request->validated(), $request->user());

        return to_route('tasks.show', $taskModel)->with('status', 'Task updated successfully.');
    }

    /** @return Builder<Task> */
    private function visibleQuery(User $user): Builder
    {
        return Task::query()->when(
            $user->role === UserRole::Salesperson,
            fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->where('assigned_to_id', $user->id)
                ->orWhere('created_by_id', $user->id)),
        );
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        return [
            'leads' => Lead::query()->with('company')->latest()->get(),
            'members' => User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'active')
                ->when($user->role === UserRole::Salesperson, fn (Builder $query) => $query->whereKey($user->id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
