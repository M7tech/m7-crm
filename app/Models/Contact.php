<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $company_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $job_title
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property string|null $notes
 * @property-read string $full_name
 * @property-read Company $company
 */
#[Fillable(['company_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'status', 'notes'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
