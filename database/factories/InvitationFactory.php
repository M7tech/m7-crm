<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invitation> */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Salesperson,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }
}
