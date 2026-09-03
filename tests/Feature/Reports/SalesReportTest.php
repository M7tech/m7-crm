<?php

namespace Tests\Feature\Reports;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PipelineProvisioner;
use App\Services\SalesReport;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_tenant_isolated_conversion_and_currency_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $won = $this->stage($pipeline, 'won');
        $lost = $this->stage($pipeline, 'lost');
        $open = $this->stage($pipeline, 'open');

        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $won->id,
            'assigned_to_id' => $manager->id,
            'title' => 'Won report lead',
            'expected_value_minor' => 125050,
            'currency' => 'USD',
        ]);
        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $lost->id,
            'assigned_to_id' => $manager->id,
            'title' => 'Lost report lead',
        ]);
        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $open->id,
            'title' => 'Open report lead',
            'expected_value_minor' => 250000000,
            'currency' => 'IQD',
        ]);
        Lead::factory()->for($otherTenant)->create(['title' => 'Secret tenant lead']);

        $this->actingAs($manager)
            ->get(route('reports.index', ['period' => '30']))
            ->assertOk()
            ->assertSee('50.0%')
            ->assertSee('1,250.50 USD')
            ->assertSee('250,000.000 IQD')
            ->assertDontSee('Secret tenant lead');
    }

    public function test_salesperson_cannot_open_management_reports(): void
    {
        $salesperson = User::factory()->create(['role' => UserRole::Salesperson]);

        $this->actingAs($salesperson)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_report_period_excludes_older_leads_and_calculates_task_completion(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $company = Company::factory()->for($tenant)->create();
        $open = $this->stage($pipeline, 'open');

        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $open->id,
            'title' => 'Current period lead',
        ]);
        Lead::factory()->for($tenant)->for($company)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $open->id,
            'title' => 'Old excluded lead',
            'created_at' => now()->subDays(60),
        ]);
        Task::factory()->for($tenant)->create([
            'assigned_to_id' => $admin->id,
            'created_by_id' => $admin->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        Task::factory()->for($tenant)->create([
            'assigned_to_id' => $admin->id,
            'created_by_id' => $admin->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index', ['period' => '30']));

        $response->assertOk()
            ->assertSee('Last 30 days')
            ->assertSee('50.0%');

        app(CurrentTenant::class)->set($tenant);
        $report = app(SalesReport::class)->build(CarbonImmutable::now()->subDays(29)->startOfDay(), $admin);
        $this->assertSame(1, $report['leadCounts']['total']);
        $this->assertSame(2, $report['tasks']['created']);
        $this->assertSame(50.0, $report['tasks']['completion_rate']);
    }

    private function stage(Pipeline $pipeline, string $type): PipelineStage
    {
        return PipelineStage::withoutGlobalScopes()
            ->where('pipeline_id', $pipeline->id)
            ->where('type', $type)
            ->orderBy('position')
            ->firstOrFail();
    }
}
