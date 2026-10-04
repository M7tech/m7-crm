<?php

namespace Tests\Feature\Agents;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_deactivate_a_statistical_agent(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)->post(route('agents.store'), [
            'tenant_id' => $otherTenant->id,
            'name' => 'Erbil Referral Office',
            'code' => 'ERB-01',
        ])->assertSessionHasNoErrors()->assertRedirect(route('agents.index'));

        $agent = Agent::query()->sole();
        $this->assertSame($tenant->id, $agent->tenant_id);
        $this->assertSame('active', $agent->status);

        $this->actingAs($manager)
            ->put(route('agents.status', $agent), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $agent->refresh()->status);
    }

    public function test_salesperson_and_other_tenant_cannot_manage_agents(): void
    {
        $tenant = Tenant::factory()->create();
        $agent = Agent::factory()->for($tenant)->create();
        $salesperson = User::factory()->for($tenant)->create(['role' => UserRole::Salesperson]);
        $otherManager = User::factory()->for(Tenant::factory()->create())->create(['role' => UserRole::SalesManager]);

        $this->actingAs($salesperson)->get(route('agents.index'))->assertForbidden();
        $this->actingAs($salesperson)->post(route('agents.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->actingAs($otherManager)
            ->put(route('agents.status', $agent), ['status' => 'inactive'])
            ->assertNotFound();
        $this->assertSame('active', $agent->refresh()->status);
    }
}
