<?php

namespace Tests\Feature\Tasks;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\PipelineProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesperson_sees_only_tasks_assigned_to_or_created_by_them(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $colleague = User::factory()->for($tenant)->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        Task::factory()->for($tenant)->create(['assigned_to_id' => $user->id, 'created_by_id' => $colleague->id, 'title' => 'Assigned task']);
        Task::factory()->for($tenant)->create(['assigned_to_id' => $colleague->id, 'created_by_id' => $user->id, 'title' => 'Created task']);
        Task::factory()->for($tenant)->create(['assigned_to_id' => $colleague->id, 'created_by_id' => $colleague->id, 'title' => 'Colleague task']);
        Task::factory()->for($otherTenant)->create(['assigned_to_id' => $otherUser->id, 'created_by_id' => $otherUser->id, 'title' => 'Other tenant task']);

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Assigned task')
            ->assertSee('Created task')
            ->assertDontSee('Colleague task')
            ->assertDontSee('Other tenant task');
    }

    public function test_manager_can_create_a_task_with_tenant_local_times(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'Asia/Baghdad']);
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $assignee = User::factory()->for($tenant)->create();

        $response = $this->actingAs($manager)->post(route('tasks.store'), [
            'tenant_id' => Tenant::factory()->create()->id,
            'title' => 'Call customer',
            'assigned_to_id' => $assignee->id,
            'due_at' => '2026-09-05T12:00',
            'reminder_at' => '2026-09-05T11:00',
            'priority' => 'high',
            'description' => 'Discuss the proposal.',
        ]);

        $task = Task::withoutGlobalScopes()->sole();
        $response->assertSessionHasNoErrors()->assertRedirect(route('tasks.show', $task));
        $this->assertSame($tenant->id, $task->tenant_id);
        $this->assertSame('2026-09-05 09:00:00', $task->due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('task_activities', [
            'tenant_id' => $tenant->id,
            'task_id' => $task->id,
            'actor_id' => $manager->id,
            'type' => 'created',
        ]);
    }

    public function test_cross_tenant_lead_and_assignee_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $otherUser = User::factory()->for($otherTenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($otherTenant);
        $lead = Lead::factory()->for($otherTenant)->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $this->stage($pipeline)->id,
        ]);

        $this->actingAs($manager)->post(route('tasks.store'), [
            'title' => 'Blocked task',
            'lead_id' => $lead->id,
            'assigned_to_id' => $otherUser->id,
            'due_at' => '2026-09-05T12:00',
            'priority' => 'normal',
        ])->assertSessionHasErrors(['lead_id', 'assigned_to_id']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Blocked task']);
    }

    public function test_salesperson_cannot_assign_a_task_to_a_colleague(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $colleague = User::factory()->for($tenant)->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Wrong assignment',
            'assigned_to_id' => $colleague->id,
            'due_at' => '2026-09-05T12:00',
            'priority' => 'normal',
        ])->assertSessionHasErrors('assigned_to_id');
    }

    public function test_assignee_can_complete_and_reopen_a_task_with_activity_history(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $task = Task::factory()->for($tenant)->create(['assigned_to_id' => $user->id, 'created_by_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('tasks.status.update', $task), ['status' => 'completed'])
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'type' => 'completed']);

        $this->actingAs($user)
            ->put(route('tasks.status.update', $task), ['status' => 'pending'])
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertNull($task->completed_at);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'type' => 'reopened']);
    }

    public function test_user_cannot_view_or_update_another_tenants_task(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $otherUser = User::factory()->for($otherTenant)->create();
        $task = Task::factory()->for($otherTenant)->create(['assigned_to_id' => $otherUser->id, 'created_by_id' => $otherUser->id]);

        $this->actingAs($user)->get(route('tasks.show', $task))->assertNotFound();
        $this->actingAs($user)->put(route('tasks.status.update', $task), ['status' => 'completed'])->assertForbidden();
    }

    public function test_task_activity_is_immutable(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $task = Task::factory()->for($tenant)->create(['assigned_to_id' => $user->id, 'created_by_id' => $user->id]);
        $activity = new TaskActivity(['task_id' => $task->id, 'type' => 'created', 'description' => 'Original']);
        $activity->tenant_id = $tenant->id;
        $activity->save();

        $this->expectException(LogicException::class);
        $activity->delete();
    }

    public function test_due_reminder_is_queued_only_once(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $task = Task::factory()->for($tenant)->create([
            'assigned_to_id' => $user->id,
            'created_by_id' => $user->id,
            'reminder_at' => now()->subMinute(),
            'reminder_sent_at' => null,
        ]);

        $this->artisan('tasks:send-reminders')->assertSuccessful();
        $this->artisan('tasks:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($user, TaskReminderNotification::class, 1);
        $this->assertNotNull($task->fresh()->reminder_sent_at);
    }

    private function stage(Pipeline $pipeline): PipelineStage
    {
        return PipelineStage::withoutGlobalScopes()
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('position')
            ->firstOrFail();
    }
}
