<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'contact_id', 'pipeline_id', 'stage_id', 'assigned_to_id', 'title', 'expected_value_minor', 'currency', 'source', 'notes', 'loss_reason', 'closed_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['expected_value_minor' => 'integer', 'closed_at' => 'datetime'];
    }

    public function formattedExpectedValue(): string
    {
        return self::formatMinorValue($this->expected_value_minor, $this->currency);
    }

    public static function formatMinorValue(int $value, string $currency): string
    {
        $divisor = $currency === 'USD' ? 100 : 1000;
        $decimals = $currency === 'USD' ? 2 : 3;

        return number_format($value / $divisor, $decimals).' '.$currency;
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
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

    /** @return HasMany<LeadActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('created_at');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
