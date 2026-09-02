<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'city' => fake()->optional()->city(),
            'notes' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }
}
