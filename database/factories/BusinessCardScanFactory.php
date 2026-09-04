<?php

namespace Database\Factories;

use App\Models\BusinessCardScan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BusinessCardScan> */
class BusinessCardScanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'public_id' => (string) Str::uuid(),
            'uploaded_by_id' => fn (array $attributes) => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'status' => 'queued',
            'disk' => 'local',
            'image_path' => 'business-card-scans/'.Str::uuid().'.jpg',
            'original_name' => 'business-card.jpg',
            'mime_type' => 'image/jpeg',
            'expires_at' => now()->addDay(),
        ];
    }
}
