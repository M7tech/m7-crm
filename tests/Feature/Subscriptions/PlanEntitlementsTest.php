<?php

namespace Tests\Feature\Subscriptions;

use App\Enums\UserRole;
use App\Models\AutomationRule;
use App\Models\Company;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PipelineProvisioner;
use App\Services\PlanEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlanEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_creation_stops_at_the_workspace_plan_limit(): void
    {
        config()->set('plans.plans.starter.limits.companies', 1);
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        Company::factory()->for($tenant)->create();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Over limit company',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('companies', ['name' => 'Over limit company']);
    }

    public function test_pending_invitations_reserve_member_capacity_but_can_be_resent(): void
    {
        Notification::fake();
        config()->set('plans.plans.starter.limits.members', 2);
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $payload = ['email' => 'member@example.com', 'role' => UserRole::Salesperson->value];

        $this->actingAs($admin)->post(route('team.invitations.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('team.invitations.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('team.invitations.store'), [
            'email' => 'another@example.com',
            'role' => UserRole::Salesperson->value,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('invitations', 1);
    }

    public function test_inactive_member_cannot_be_reactivated_beyond_the_plan_limit(): void
    {
        config()->set('plans.plans.starter.limits.members', 1);
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $inactive = User::factory()->for($tenant)->create(['status' => 'inactive']);

        $this->actingAs($admin)->put(route('team.members.update', $inactive), [
            'role' => UserRole::Salesperson->value,
            'status' => 'active',
        ])->assertSessionHasErrors('status');

        $this->assertSame('inactive', $inactive->refresh()->status);
    }

    public function test_automation_creation_uses_the_central_plan_limit(): void
    {
        config()->set('plans.plans.starter.limits.automation_rules', 1);
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->firstOrFail();
        $rule = new AutomationRule([
            'stage_id' => $stage->id,
            'created_by_id' => $admin->id,
            'name' => 'Existing rule',
            'task_title' => 'Call {lead}',
            'due_days' => 1,
            'priority' => 'normal',
            'assignee_strategy' => 'lead_owner',
        ]);
        $rule->tenant_id = $tenant->id;
        $rule->save();

        $this->actingAs($admin)->post(route('automations.store'), [
            'name' => 'Over limit rule',
            'stage_id' => $stage->id,
            'task_title' => 'Email {lead}',
            'due_days' => 2,
            'priority' => 'normal',
            'assignee_strategy' => 'lead_owner',
        ])->assertSessionHasErrors('name');

        $this->assertSame(1, AutomationRule::withoutGlobalScopes()->whereNull('deleted_at')->count());
    }

    public function test_meta_connection_creation_stops_at_the_plan_limit(): void
    {
        config()->set('plans.plans.starter.limits.meta_connections', 0);
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $company = Company::factory()->for($tenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'open')->firstOrFail();

        $this->actingAs($admin)->post(route('integrations.meta.store'), [
            'name' => 'Over limit Meta Page',
            'app_id' => '123456789',
            'app_secret' => 'long-enough-app-secret',
            'configuration_id' => '987654321',
            'graph_version' => 'v26.0',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_plan_usage_is_calculated_only_for_the_requested_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        User::factory()->for($tenant)->create();
        User::factory()->count(3)->for($otherTenant)->create();
        Company::factory()->for($tenant)->create();
        Company::factory()->count(4)->for($otherTenant)->create();
        $plans = app(PlanEntitlements::class);

        $this->assertSame(1, $plans->usage($tenant, 'members'));
        $this->assertSame(1, $plans->usage($tenant, 'companies'));
        $this->assertSame(3, $plans->usage($otherTenant, 'members'));
        $this->assertSame(4, $plans->usage($otherTenant, 'companies'));
    }

    public function test_company_admin_sees_onboarding_and_plan_usage_on_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'starter']);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Workspace setup')
            ->assertSee('0 of 5 steps complete')
            ->assertSee('Starter plan usage')
            ->assertSee('Add your first company');
    }
}
