<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $integration_id
 * @property int $company_id
 * @property int|null $contact_id
 * @property int|null $assigned_to_id
 * @property string $channel
 * @property string $status
 * @property Carbon|null $last_message_at
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $resolved_at
 */
#[Fillable(['integration_id', 'company_id', 'contact_id', 'assigned_to_id', 'assigned_at', 'channel', 'external_thread_id', 'external_participant_id', 'participant_name', 'status', 'last_message_at', 'last_inbound_at', 'resolved_at'])]
class Conversation extends Model
{
    use BelongsToTenant;

    /** Statuses an employee can choose for a conversation. */
    public const STATUSES = ['open', 'pending', 'resolved'];

    /** Channels the CRM can reply to on behalf of the connected account. */
    public const REPLYABLE_CHANNELS = ['facebook_messenger', 'facebook_comments'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Record an inbound customer message. A new customer message reopens a
     * pending or resolved conversation so it returns to the shared queue.
     */
    public function recordInbound(CarbonInterface $sentAt): void
    {
        $changes = [];

        if ($this->last_message_at === null || $sentAt->greaterThan($this->last_message_at)) {
            $changes['last_message_at'] = $sentAt;
        }

        if ($this->last_inbound_at === null || $sentAt->greaterThan($this->last_inbound_at)) {
            $changes['last_inbound_at'] = $sentAt;

            if ($this->status !== 'open') {
                $changes['status'] = 'open';
                $changes['resolved_at'] = null;
            }
        }

        if ($changes !== []) {
            $this->update($changes);
        }
    }

    public function isUnreadFor(User $user): bool
    {
        if ($this->last_inbound_at === null) {
            return false;
        }

        $read = $this->relationLoaded('currentRead')
            ? $this->getRelation('currentRead')
            : $this->reads()->where('user_id', $user->id)->first();

        return ! $read instanceof ConversationRead || $read->last_read_at->lessThan($this->last_inbound_at);
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            'facebook_comments' => __('Facebook comment'),
            'facebook_messenger' => __('Messenger'),
            default => ucfirst(str_replace('_', ' ', $this->channel)),
        };
    }

    public function replyVisibility(): string
    {
        return $this->channel === 'facebook_comments' ? 'public' : 'private';
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->whereNotNull('conversations.last_inbound_at')
            ->whereNotExists(fn ($reads) => $reads->selectRaw('1')
                ->from('conversation_reads')
                ->whereColumn('conversation_reads.conversation_id', 'conversations.id')
                ->where('conversation_reads.user_id', $user->id)
                ->whereColumn('conversation_reads.last_read_at', '>=', 'conversations.last_inbound_at'));
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
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

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasOne<Message, $this> */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    /** @return HasMany<ConversationActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ConversationActivity::class);
    }

    /** @return HasMany<ConversationRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    /** @return HasOne<ConversationRead, $this> */
    public function currentRead(): HasOne
    {
        return $this->hasOne(ConversationRead::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
