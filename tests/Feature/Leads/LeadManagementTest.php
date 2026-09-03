<?php

namespace Tests\Feature\Leads;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PipelineProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_leads_from_their_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $otherPipeline = app(PipelineProvisioner::class)->createDefault($otherTenant);
        $company = Company::factory()->for($tenant)->create();
        $otherCompany = Company::factory()->for($otherTenant)->create();
        $user = User::factory()->for($tenant)->create();

        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline)->id,
            'title' => 'Visible opportunity',
        ]);
        Lead::factory()->for($otherTenant)->for($otherCompany)->create([
            'pipeline_id' => $otherPipeline->id,
            'stage_id' => $this->stage($otherPipeline)->id,
            'title' => 'Hidden opportunity',
        ]);

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Visible opportunity')
            ->assertDontSee('Hidden opportunity');
    }

    public function test_user_can_create_a_lead_with_assignment_and_expected_value(): void
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = $this->stage($pipeline);
        $company = Company::factory()->for($tenant)->create();
        $contact = Contact::factory()->for($tenant)->for($company)->create();
        $user = User::factory()->for($tenant)->create();

        $response = $this->actingAs($user)->post(route('leads.store'), [
            'tenant_id' => Tenant::factory()->create()->id,
            'title' => 'Website redesign',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to_id' => $user->id,
            'expected_value' => '1250.75',
            'currency' => 'USD',
            'source' => 'Referral',
            'notes' => 'Follow up next week.',
        ]);

        $lead = Lead::withoutGlobalScopes()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('leads.show', $lead));
        $this->assertSame($tenant->id, $lead->tenant_id);
        $this->assertSame(125075, $lead->expected_value_minor);
        $this->assertDatabaseHas('lead_activities', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'actor_id' => $user->id,
            'type' => 'created',
        ]);
    }

    public function test_cross_tenant_relationships_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $otherPipeline = app(PipelineProvisioner::class)->createDefault($otherTenant);
        $company = Company::factory()->for($tenant)->create();
        $otherCompany = Company::factory()->for($otherTenant)->create();
        $otherContact = Contact::factory()->for($otherTenant)->for($otherCompany)->create();
        $otherUser = User::factory()->for($otherTenant)->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)->post(route('leads.store'), [
            'title' => 'Blocked opportunity',
            'company_id' => $otherCompany->id,
            'contact_id' => $otherContact->id,
            'pipeline_id' => $otherPipeline->id,
            'stage_id' => $this->stage($otherPipeline)->id,
            'assigned_to_id' => $otherUser->id,
            'expected_value' => 100,
            'currency' => 'IQD',
        ])->assertSessionHasErrors(['company_id', 'contact_id', 'pipeline_id', 'stage_id', 'assigned_to_id']);

        $this->assertDatabaseMissing('leads', ['title' => 'Blocked opportunity']);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('pipelines', ['id' => $pipeline->id]);
    }

    public function test_moving_a_lead_to_lost_requires_a_reason_and_records_activity(): void
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $user = User::factory()->for($tenant)->create();
        $lead = Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline, 'open')->id,
        ]);
        $lost = $this->stage($pipeline, 'lost');

        $this->actingAs($user)
            ->put(route('leads.stage.update', $lead), ['stage_id' => $lost->id])
            ->assertSessionHasErrors('loss_reason');

        $this->actingAs($user)
            ->put(route('leads.stage.update', $lead), [
                'stage_id' => $lost->id,
                'loss_reason' => 'Budget was cancelled',
            ])->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertSame($lost->id, $lead->stage_id);
        $this->assertSame('Budget was cancelled', $lead->loss_reason);
        $this->assertNotNull($lead->closed_at);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'stage_changed']);
    }

    public function test_moving_a_closed_lead_back_to_an_open_stage_clears_outcome(): void
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $user = User::factory()->for($tenant)->create();
        $lead = Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline, 'lost')->id,
            'closed_at' => now(),
            'loss_reason' => 'Old reason',
        ]);
        $open = $this->stage($pipeline, 'open');

        $this->actingAs($user)
            ->put(route('leads.stage.update', $lead), ['stage_id' => $open->id])
            ->assertSessionHasNoErrors();

        $lead->refresh();
        $this->assertNull($lead->closed_at);
        $this->assertNull($lead->loss_reason);
    }

    public function test_lead_activity_cannot_be_changed(): void
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $lead = Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline)->id,
        ]);
        $activity = new LeadActivity(['lead_id' => $lead->id, 'type' => 'created', 'description' => 'Original']);
        $activity->tenant_id = $tenant->id;
        $activity->save();

        $this->expectException(LogicException::class);
        $activity->update(['description' => 'Changed']);
    }

    public function test_lead_activity_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $lead = Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline)->id,
        ]);
        $activity = new LeadActivity(['lead_id' => $lead->id, 'type' => 'created', 'description' => 'Original']);
        $activity->tenant_id = $tenant->id;
        $activity->save();

        $this->expectException(LogicException::class);
        $activity->delete();
    }

    public function test_managers_can_create_pipelines_but_salespeople_cannot(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)->post(route('pipelines.store'), [
            'name' => 'Enterprise Sales',
            'stages_text' => "Discovery\nDemo\nNegotiation",
        ])->assertSessionHasNoErrors()->assertRedirect(route('pipelines.index'));

        $pipeline = Pipeline::withoutGlobalScopes()->where('name', 'Enterprise Sales')->firstOrFail();
        $this->assertSame($tenant->id, $pipeline->tenant_id);
        $this->assertDatabaseHas('pipeline_stages', ['pipeline_id' => $pipeline->id, 'name' => 'Won', 'type' => 'won']);
        $this->assertDatabaseHas('pipeline_stages', ['pipeline_id' => $pipeline->id, 'name' => 'Lost', 'type' => 'lost']);

        $salesperson = User::factory()->for($tenant)->create(['role' => UserRole::Salesperson]);
        $this->actingAs($salesperson)->post(route('pipelines.store'), [
            'name' => 'Forbidden Pipeline',
            'stages_text' => 'New',
        ])->assertForbidden();
        $this->assertDatabaseMissing('pipelines', ['name' => 'Forbidden Pipeline']);
    }

    private function stage(Pipeline $pipeline, ?string $type = null): PipelineStage
    {
        return PipelineStage::withoutGlobalScopes()
            ->where('pipeline_id', $pipeline->id)
            ->when($type, fn ($query, string $value) => $query->where('type', $value))
            ->orderBy('position')
            ->firstOrFail();
    }
}
