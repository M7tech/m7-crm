<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->bothify('AG-###'),
            'status' => 'active',
        ];
    }
}
