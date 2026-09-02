<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property string $plan
 * @property string $timezone
 * @property string $locale
 */
#[Fillable(['name', 'slug', 'status', 'plan', 'timezone', 'locale'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Company, $this> */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
