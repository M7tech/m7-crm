<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => fn (array $attributes) => Company::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'pipeline_id' => fn (array $attributes) => Pipeline::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'stage_id' => function (array $attributes): int {
                $stage = new PipelineStage([
                    'pipeline_id' => $attributes['pipeline_id'],
                    'name' => 'New',
                    'position' => 1,
                    'type' => 'open',
                    'color' => 'sky',
                ]);
                $stage->tenant_id = $attributes['tenant_id'];
                $stage->save();

                return $stage->id;
            },
            'contact_id' => null,
            'assigned_to_id' => null,
            'title' => fake()->sentence(4),
            'expected_value_minor' => fake()->numberBetween(0, 100000000),
            'currency' => 'IQD',
            'source' => fake()->optional()->randomElement(['Referral', 'Website', 'Outbound']),
            'notes' => fake()->optional()->sentence(),
            'loss_reason' => null,
            'closed_at' => null,
        ];
    }
}
