<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'status'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToTenant, HasFactory;

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
