<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['integration_id', 'provider', 'external_id', 'event_type', 'payload', 'status', 'attempts', 'error', 'processed_at'])]
class WebhookEvent extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'processed_at' => 'datetime'];
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
