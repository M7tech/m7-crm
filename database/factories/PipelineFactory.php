<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pipeline> */
class PipelineFactory extends Factory
{
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->words(2, true), 'is_default' => false];
    }
}
