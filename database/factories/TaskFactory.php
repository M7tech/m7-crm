<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lead_id' => null,
            'assigned_to_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'created_by_id' => fn (array $attributes) => User::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'due_at' => now()->addDays(2),
            'reminder_at' => null,
            'priority' => 'normal',
            'status' => 'pending',
            'completed_at' => null,
        ];
    }
}
