<?php

namespace App\Jobs;

use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\LeadActivity;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunLeadAutomations implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public int $leadActivityId,
        public int $tenantId,
    ) {}

    public function handle(CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $activity = LeadActivity::query()->with('lead')->findOrFail($this->leadActivityId);
        $stageId = $activity->type === 'created'
            ? data_get($activity->metadata, 'stage_id')
            : data_get($activity->metadata, 'to_stage_id');

        if (! is_int($stageId) && ! ctype_digit((string) $stageId)) {
            return;
        }

        $rules = AutomationRule::query()
            ->where('is_active', true)
            ->where('trigger_type', 'lead_entered_stage')
            ->where('stage_id', (int) $stageId)
            ->get();

        foreach ($rules as $rule) {
            $this->run($rule, $activity);
        }
    }

    private function run(AutomationRule $rule, LeadActivity $activity): void
    {
        try {
            DB::transaction(function () use ($rule, $activity): void {
                $run = AutomationRun::firstOrCreate([
                    'automation_rule_id' => $rule->id,
                    'lead_activity_id' => $activity->id,
                ], [
                    'lead_id' => $activity->lead_id,
                    'status' => 'queued',
                ]);

                $run = AutomationRun::query()->lockForUpdate()->findOrFail($run->id);
                if ($run->status === 'completed') {
                    return;
                }

                $run->update(['status' => 'running', 'error' => null, 'started_at' => now(), 'completed_at' => null]);
                $lead = $activity->lead;
                $task = Task::create([
                    'lead_id' => $lead->id,
                    'assigned_to_id' => $rule->assignee_strategy === 'lead_owner' ? $lead->assigned_to_id : null,
                    'created_by_id' => $rule->created_by_id,
                    'title' => Str::limit(str_replace('{lead}', $lead->title, $rule->task_title), 180, ''),
                    'description' => 'Created automatically when the lead entered '.$rule->stage->name.'.',
                    'due_at' => now()->addDays($rule->due_days),
                    'priority' => $rule->priority,
                    'status' => 'pending',
                ]);
                TaskActivity::create([
                    'task_id' => $task->id,
                    'actor_id' => $rule->created_by_id,
                    'type' => 'created',
                    'description' => 'Task created by automation: '.$rule->name.'.',
                    'metadata' => ['automation_rule_id' => $rule->id, 'automation_run_id' => $run->id],
                ]);
                $run->update(['task_id' => $task->id, 'status' => 'completed', 'completed_at' => now()]);
            });
        } catch (Throwable $exception) {
            AutomationRun::updateOrCreate([
                'automation_rule_id' => $rule->id,
                'lead_activity_id' => $activity->id,
            ], [
                'lead_id' => $activity->lead_id,
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
