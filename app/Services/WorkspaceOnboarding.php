<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Lead;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;

class WorkspaceOnboarding
{
    /** @return array{completed: int, total: int, steps: array<int, array{label: string, description: string, complete: bool, url: string}>} */
    public function summary(Tenant $tenant): array
    {
        $hasTeammate = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count() > 1
            || Invitation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->exists();
        $steps = [
            [
                'label' => 'Add your first company',
                'description' => 'Create the first customer business account.',
                'complete' => Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists(),
                'url' => route('companies.index'),
            ],
            [
                'label' => 'Create your first lead',
                'description' => 'Add an opportunity to the sales pipeline.',
                'complete' => Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists(),
                'url' => route('leads.create'),
            ],
            [
                'label' => 'Schedule a follow-up',
                'description' => 'Create a task with a due date and owner.',
                'complete' => Task::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists(),
                'url' => route('tasks.create'),
            ],
            [
                'label' => 'Invite a teammate',
                'description' => 'Bring a manager or salesperson into the workspace.',
                'complete' => $hasTeammate,
                'url' => route('team.index'),
            ],
            [
                'label' => 'Create an automation',
                'description' => 'Automatically create a task when a lead changes stage.',
                'complete' => AutomationRule::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->exists(),
                'url' => route('automations.index'),
            ],
        ];

        return [
            'completed' => collect($steps)->where('complete', true)->count(),
            'total' => count($steps),
            'steps' => $steps,
        ];
    }
}
