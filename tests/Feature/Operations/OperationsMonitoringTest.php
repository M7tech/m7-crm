<?php

namespace Tests\Feature\Operations;

use App\Jobs\RecordQueueWorkerHeartbeat;
use App\Models\SystemHealthCheck;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class OperationsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_view_cross_workspace_operations(): void
    {
        $activeTenant = Tenant::factory()->create(['name' => 'Active workspace']);
        Tenant::factory()->create(['status' => 'suspended']);
        User::factory()->for($activeTenant)->companyAdmin()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        SystemHealthCheck::record('scheduler');
        SystemHealthCheck::record('queue_worker');
        DB::table('failed_jobs')->insert([
            'uuid' => fake()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob'], JSON_THROW_ON_ERROR),
            'exception' => "Failure summary\nStack trace is intentionally hidden",
            'failed_at' => now(),
        ]);
        $redis = Mockery::mock();
        $redis->shouldReceive('command')->once()->with('ping')->andReturn('PONG');
        Redis::shouldReceive('connection')->once()->andReturn($redis);

        $this->actingAs($superAdmin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee('Service health')
            ->assertSee('Active workspaces')
            ->assertSee('App\Jobs\ExampleJob')
            ->assertSee('Failure summary')
            ->assertDontSee('Stack trace is intentionally hidden');

        $this->actingAs(User::factory()->for($activeTenant)->companyAdmin()->create())
            ->get(route('operations.index'))
            ->assertForbidden();
    }

    public function test_scheduled_command_records_scheduler_and_queues_worker_heartbeat(): void
    {
        Queue::fake();

        $this->assertSame(0, Artisan::call('system:heartbeat'));

        $this->assertDatabaseHas('system_health_checks', ['key' => 'scheduler']);
        Queue::assertPushed(RecordQueueWorkerHeartbeat::class);
    }

    public function test_worker_job_records_its_own_heartbeat(): void
    {
        (new RecordQueueWorkerHeartbeat)->handle();

        $this->assertDatabaseHas('system_health_checks', [
            'key' => 'queue_worker',
        ]);
    }
}
