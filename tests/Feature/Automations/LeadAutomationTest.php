<?php

namespace Tests\Feature\Automations;

use App\Enums\UserRole;
use App\Jobs\RunLeadAutomations;
use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PipelineProvisioner;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_a_rule_but_salesperson_cannot(): void
    {
        [$tenant, $stage] = $this->workspace();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $salesperson = User::factory()->for($tenant)->create(['role' => UserRole::Salesperson]);
        $data = [
            'name' => 'Qualified follow-up',
            'stage_id' => $stage->id,
            'task_title' => 'Call {lead}',
            'due_days' => 2,
            'priority' => 'high',
            'assignee_strategy' => 'lead_owner',
        ];

        $this->actingAs($salesperson)->post(route('automations.store'), $data)->assertForbidden();
        $this->actingAs($manager)
            ->post(route('automations.store'), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('automations.index'));

        $rule = AutomationRule::withoutGlobalScopes()->sole();
        $this->assertSame($tenant->id, $rule->tenant_id);
        $this->assertSame($manager->id, $rule->created_by_id);
        $this->assertTrue($rule->is_active);
    }

    public function test_rule_rejects_a_stage_from_another_tenant(): void
    {
        [$tenant] = $this->workspace();
        [, $otherStage] = $this->workspace();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)->post(route('automations.store'), [
            'name' => 'Cross tenant rule',
            'stage_id' => $otherStage->id,
            'task_title' => 'Call {lead}',
            'due_days' => 1,
            'priority' => 'normal',
            'assignee_strategy' => 'lead_owner',
        ])->assertSessionHasErrors('stage_id');

        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_lead_activity_queues_automation_processing(): void
    {
        Queue::fake();
        [$tenant, $stage, $lead, $manager] = $this->workspaceWithLead();
        $activity = new LeadActivity([
            'lead_id' => $lead->id,
            'actor_id' => $manager->id,
            'type' => 'stage_changed',
            'description' => 'Lead moved.',
            'metadata' => ['to_stage_id' => $stage->id],
        ]);
        $activity->tenant_id = $tenant->id;
        $activity->save();

        Queue::assertPushed(RunLeadAutomations::class, fn (RunLeadAutomations $job): bool => $job->leadActivityId === $activity->id
            && $job->tenantId === $tenant->id);
    }

    public function test_matching_rule_creates_one_audited_task_when_retried(): void
    {
        Queue::fake();
        [$tenant, $stage, $lead, $manager] = $this->workspaceWithLead();
        $rule = new AutomationRule([
            'stage_id' => $stage->id,
            'created_by_id' => $manager->id,
            'name' => 'Qualified follow-up',
            'trigger_type' => 'lead_entered_stage',
            'action_type' => 'create_task',
            'task_title' => 'Call {lead}',
            'due_days' => 2,
            'priority' => 'high',
            'assignee_strategy' => 'lead_owner',
            'is_active' => true,
        ]);
        $rule->tenant_id = $tenant->id;
        $rule->save();
        $activity = new LeadActivity([
            'lead_id' => $lead->id,
            'actor_id' => $manager->id,
            'type' => 'stage_changed',
            'description' => 'Lead moved.',
            'metadata' => ['to_stage_id' => $stage->id],
        ]);
        $activity->tenant_id = $tenant->id;
        $activity->save();
        $job = new RunLeadAutomations($activity->id, $tenant->id);

        $job->handle(app(CurrentTenant::class));
        $job->handle(app(CurrentTenant::class));

        $task = Task::query()->sole();
        $this->assertSame('Call '.$lead->title, $task->title);
        $this->assertSame($manager->id, $task->assigned_to_id);
        $this->assertSame('high', $task->priority);
        $this->assertTrue($task->due_at->between(now()->addDays(2)->subMinute(), now()->addDays(2)->addMinute()));
        $this->assertDatabaseCount('automation_runs', 1);
        $this->assertDatabaseHas('automation_runs', [
            'automation_rule_id' => $rule->id,
            'lead_activity_id' => $activity->id,
            'task_id' => $task->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'type' => 'created',
        ]);
    }

    public function test_automation_page_is_tenant_isolated(): void
    {
        [$tenant, $stage] = $this->workspace();
        [$otherTenant, $otherStage] = $this->workspace();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $otherManager = User::factory()->for($otherTenant)->create(['role' => UserRole::SalesManager]);
        $this->rule($tenant, $stage, $manager, 'Visible automation');
        $this->rule($otherTenant, $otherStage, $otherManager, 'Hidden automation');

        $this->actingAs($manager)
            ->get(route('automations.index'))
            ->assertOk()
            ->assertSee('Visible automation')
            ->assertDontSee('Hidden automation');
    }

    /** @return array{Tenant, PipelineStage} */
    private function workspace(): array
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'open')->firstOrFail();

        return [$tenant, $stage];
    }

    /** @return array{Tenant, PipelineStage, Lead, User} */
    private function workspaceWithLead(): array
    {
        [$tenant, $stage] = $this->workspace();
        $company = Company::factory()->for($tenant)->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $lead = Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'assigned_to_id' => $manager->id,
        ]);

        return [$tenant, $stage, $lead, $manager];
    }

    private function rule(Tenant $tenant, PipelineStage $stage, User $creator, string $name): AutomationRule
    {
        $rule = new AutomationRule([
            'stage_id' => $stage->id,
            'created_by_id' => $creator->id,
            'name' => $name,
            'trigger_type' => 'lead_entered_stage',
            'action_type' => 'create_task',
            'task_title' => 'Call {lead}',
            'due_days' => 1,
            'priority' => 'normal',
            'assignee_strategy' => 'lead_owner',
            'is_active' => true,
        ]);
        $rule->tenant_id = $tenant->id;
        $rule->save();

        return $rule;
    }
}
