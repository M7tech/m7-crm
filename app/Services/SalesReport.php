<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SalesReport
{
    /** @return array<string, mixed> */
    public function build(?CarbonImmutable $start, User $user): array
    {
        $leads = Lead::query()
            ->with(['stage', 'assignedTo'])
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->get();
        $won = $leads->filter(fn (Lead $lead) => $lead->stage->type === 'won');
        $lost = $leads->filter(fn (Lead $lead) => $lead->stage->type === 'lost');
        $open = $leads->filter(fn (Lead $lead) => $lead->stage->type === 'open');
        $decided = $won->count() + $lost->count();

        $createdTasks = Task::query()
            ->visibleTo($user)
            ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
            ->get();
        $completedTasks = $createdTasks->where('status', 'completed')->count();

        return [
            'leadCounts' => [
                'total' => $leads->count(),
                'open' => $open->count(),
                'won' => $won->count(),
                'lost' => $lost->count(),
            ],
            'winRate' => $decided > 0 ? round(($won->count() / $decided) * 100, 1) : 0.0,
            'values' => [
                'open' => $this->currencyTotals($open),
                'won' => $this->currencyTotals($won),
            ],
            'pipelines' => $this->pipelineRows($leads),
            'assignees' => $this->assigneeRows($leads),
            'tasks' => [
                'created' => $createdTasks->count(),
                'completed' => $completedTasks,
                'completion_rate' => $createdTasks->isNotEmpty() ? round(($completedTasks / $createdTasks->count()) * 100, 1) : 0.0,
                'overdue' => Task::query()->visibleTo($user)->where('status', 'pending')->where('due_at', '<', now())->count(),
            ],
        ];
    }

    /** @param Collection<int, Lead> $leads @return array<string, int> */
    private function currencyTotals(Collection $leads): array
    {
        return [
            'IQD' => (int) $leads->where('currency', 'IQD')->sum('expected_value_minor'),
            'USD' => (int) $leads->where('currency', 'USD')->sum('expected_value_minor'),
        ];
    }

    /** @param Collection<int, Lead> $leads @return Collection<int, array<string, mixed>> */
    private function pipelineRows(Collection $leads): Collection
    {
        return Pipeline::query()->with('stages')->orderByDesc('is_default')->get()->map(function (Pipeline $pipeline) use ($leads): array {
            $pipelineLeads = $leads->where('pipeline_id', $pipeline->id);
            $maximum = max(1, ...$pipeline->stages->map(fn ($stage) => $pipelineLeads->where('stage_id', $stage->id)->count())->all());

            return [
                'name' => $pipeline->name,
                'total' => $pipelineLeads->count(),
                'stages' => $pipeline->stages->map(function ($stage) use ($pipelineLeads, $maximum): array {
                    $count = $pipelineLeads->where('stage_id', $stage->id)->count();

                    return [
                        'name' => $stage->name,
                        'type' => $stage->type,
                        'count' => $count,
                        'width' => round(($count / $maximum) * 100),
                    ];
                }),
            ];
        });
    }

    /** @param Collection<int, Lead> $leads @return Collection<int, array<string, mixed>> */
    private function assigneeRows(Collection $leads): Collection
    {
        return $leads->groupBy(fn (Lead $lead) => $lead->assigned_to_id ?? 'unassigned')
            ->map(function (Collection $assignedLeads): array {
                $won = $assignedLeads->filter(fn (Lead $lead) => $lead->stage->type === 'won')->count();
                $lost = $assignedLeads->filter(fn (Lead $lead) => $lead->stage->type === 'lost')->count();
                $decided = $won + $lost;

                return [
                    'name' => $assignedLeads->first()?->assignedTo?->name ?? 'Unassigned',
                    'total' => $assignedLeads->count(),
                    'won' => $won,
                    'lost' => $lost,
                    'win_rate' => $decided > 0 ? round(($won / $decided) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('won')
            ->values();
    }
}
