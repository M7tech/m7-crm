<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $public_id
 * @property string $status
 * @property array<string, mixed> $credentials Encrypted at rest; never rendered back to forms.
 * @property array<string, mixed> $settings
 * @property string|null $external_account_id
 * @property string|null $external_account_name
 */
#[Fillable(['public_id', 'provider', 'name', 'status', 'credentials', 'settings', 'external_account_id', 'external_account_name', 'company_id', 'pipeline_id', 'stage_id', 'assigned_to_id', 'connected_at', 'connected_by_id', 'disconnected_at', 'disconnected_by_id'])]
class Integration extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'settings' => 'array', 'connected_at' => 'datetime', 'disconnected_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<User, $this> */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function disconnectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disconnected_by_id');
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return HasMany<WebhookEvent, $this> */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
