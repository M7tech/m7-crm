<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Company;
use App\Models\Contact;
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
        $scope = $request->query('scope') === 'mine' ? 'mine' : 'team';
        $visible = $this->visibleQuery($request->user())
            ->when($scope === 'mine', fn (Builder $query) => $query->where('assigned_to_id', $request->user()->id));
        $filter = in_array($request->query('filter'), ['pending', 'today', 'overdue', 'completed'], true)
            ? (string) $request->query('filter')
            : 'pending';

        $tasks = (clone $visible)
            ->with(['lead.company', 'assignedTo', 'company:id,tenant_id,name', 'contact:id,tenant_id,company_id,first_name,last_name'])
            ->when($filter === 'pending', fn (Builder $query) => $query->whereIn('status', Task::OPEN_STATUSES))
            ->when($filter === 'today', fn (Builder $query) => $query->whereIn('status', Task::OPEN_STATUSES)->whereBetween('due_at', [$startOfToday, $endOfToday]))
            ->when($filter === 'overdue', fn (Builder $query) => $query->whereIn('status', Task::OPEN_STATUSES)->where('due_at', '<', now()))
            ->when($filter === 'completed', fn (Builder $query) => $query->where('status', 'completed'))
            ->orderByRaw("case when priority = 'urgent' then 1 when priority = 'high' then 2 when priority = 'normal' then 3 else 4 end")
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filter' => $filter,
            'scope' => $scope,
            'timezone' => $timezone,
            'counts' => [
                'pending' => (clone $visible)->whereIn('status', Task::OPEN_STATUSES)->count(),
                'today' => (clone $visible)->whereIn('status', Task::OPEN_STATUSES)->whereBetween('due_at', [$startOfToday, $endOfToday])->count(),
                'overdue' => (clone $visible)->whereIn('status', Task::OPEN_STATUSES)->where('due_at', '<', now())->count(),
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
            'selectedContactId' => $request->integer('contact') ?: null,
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
        $taskModel = Task::query()->with(['lead.company', 'assignedTo', 'createdBy', 'activities.actor', 'company', 'contact', 'conversation:id,tenant_id,participant_name,channel'])->findOrFail($task);
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
            'selectedContactId' => null,
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
        return Task::query()->visibleTo($user);
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        return [
            'leads' => Lead::query()->with('company')->latest()->get(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'tenant_id', 'name']),
            'contacts' => Contact::query()->with('company:id,tenant_id,name')->orderBy('first_name')->limit(500)->get(['id', 'tenant_id', 'company_id', 'first_name', 'last_name']),
            'members' => User::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'active')
                ->when($user->role === UserRole::Salesperson, fn (Builder $query) => $query->whereKey($user->id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
