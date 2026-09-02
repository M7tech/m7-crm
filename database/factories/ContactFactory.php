<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => fn (array $attributes) => Company::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => fake()->optional()->jobTitle(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
