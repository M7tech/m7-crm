<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit and internal-note trail for a conversation.
 *
 * Types: note, assigned, status_changed, reply_queued, reply_failed,
 * contact_linked, task_created.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int|null $actor_id
 * @property string $type
 * @property string|null $body
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
#[Fillable(['conversation_id', 'actor_id', 'type', 'body', 'metadata'])]
class ConversationActivity extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Conversation activities are immutable.'));
        static::deleting(fn () => throw new LogicException('Conversation activities are immutable.'));
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
