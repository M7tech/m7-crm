<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Kanban view over the same task records as My Tasks and the team list.
 */
class TaskBoardController extends Controller
{
    private const COLUMN_LIMIT = 100;

    public function __invoke(Request $request, CurrentTenant $currentTenant): View
    {
        $this->authorize('viewAny', Task::class);
        $user = $request->user();
        $scope = $request->query('scope') === 'mine' ? 'mine' : 'team';

        $base = Task::query()
            ->visibleTo($user)
            ->when($scope === 'mine', fn (Builder $query) => $query->where('assigned_to_id', $user->id));

        $columns = collect(Task::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (clone $base)
                ->with(['assignedTo:id,name', 'company:id,tenant_id,name', 'contact:id,tenant_id,company_id,first_name,last_name', 'lead:id,tenant_id,title'])
                ->where('status', $status)
                ->when(
                    $status === 'completed',
                    fn (Builder $query) => $query->latest('completed_at'),
                    fn (Builder $query) => $query
                        ->orderByRaw("case when priority = 'urgent' then 1 when priority = 'high' then 2 when priority = 'normal' then 3 else 4 end")
                        ->orderBy('due_at'),
                )
                ->limit(self::COLUMN_LIMIT)
                ->get(),
        ]);

        return view('tasks.board', [
            'columns' => $columns,
            'counts' => collect(Task::STATUSES)->mapWithKeys(fn (string $status) => [$status => (clone $base)->where('status', $status)->count()]),
            'scope' => $scope,
            'timezone' => $currentTenant->tenant()->timezone ?? 'Asia/Baghdad',
        ]);
    }
}
