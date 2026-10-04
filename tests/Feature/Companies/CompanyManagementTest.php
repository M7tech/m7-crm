<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PipelineProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_companies_from_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        Company::factory()->for($tenant)->create(['name' => 'Atlas Trading']);
        Company::factory()->for($otherTenant)->create(['name' => 'Hidden Company']);

        $response = $this->actingAs($user)->get(route('companies.index'));

        $response->assertOk()
            ->assertSee('Atlas Trading')
            ->assertDontSee('Hidden Company');
    }

    public function test_created_company_is_assigned_to_authenticated_users_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $response = $this->actingAs($user)->post(route('companies.store'), [
            'tenant_id' => $otherTenant->id,
            'name' => 'Iraq Build',
            'phone' => '07500000000',
            'city' => 'Erbil',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.index'));

        $this->assertDatabaseHas('companies', [
            'tenant_id' => $tenant->id,
            'name' => 'Iraq Build',
        ]);
        $this->assertDatabaseMissing('companies', [
            'tenant_id' => $otherTenant->id,
            'name' => 'Iraq Build',
        ]);
    }

    public function test_company_directory_distinguishes_customers_from_prospects_using_won_leads(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $won = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'won')->firstOrFail();
        $buyer = Company::factory()->for($tenant)->create(['name' => 'Existing Buyer']);
        Company::factory()->for($tenant)->create(['name' => 'New Prospect']);
        Lead::factory()->for($tenant)->for($buyer)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $won->id,
            'closed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Existing Buyer')
            ->assertSee('New Prospect')
            ->assertSee('Customer')
            ->assertSee('Prospect');
    }

    public function test_company_creation_is_validated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('companies.store'), [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['name', 'email']);
    }
}
