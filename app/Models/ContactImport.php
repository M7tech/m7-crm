<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'imported_by_id', 'original_name', 'status', 'duplicate_strategy', 'preview_token_hash',
    'total_rows', 'ready_rows', 'duplicate_rows', 'invalid_rows', 'imported_rows',
    'updated_rows', 'skipped_rows', 'failure_summary', 'completed_at',
])]
class ContactImport extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'failure_summary' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }
}
