<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $city
 * @property string|null $notes
 * @property string $status
 */
#[Fillable(['name', 'phone', 'email', 'city', 'notes', 'status'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToTenant, HasFactory;
}
