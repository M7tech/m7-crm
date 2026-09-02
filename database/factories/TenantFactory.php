<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => TenantStatus::Active,
            'plan' => 'starter',
            'timezone' => 'Asia/Baghdad',
            'locale' => 'en',
        ];
    }
}
