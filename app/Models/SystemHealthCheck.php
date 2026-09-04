<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'checked_at', 'payload'])]
class SystemHealthCheck extends Model
{
    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $key, array $payload = []): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['checked_at' => now(), 'payload' => $payload],
        );
    }
}
