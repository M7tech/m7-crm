<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $invited_by_id
 * @property string $email
 * @property UserRole $role
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Tenant $tenant
 * @property-read User|null $invitedBy
 */
#[Fillable(['invited_by_id', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }
}
