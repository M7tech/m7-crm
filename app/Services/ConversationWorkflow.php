<?php

namespace App\Services;

use App\Exceptions\ConcurrentReplyException;
use App\Jobs\SendMetaCommentReply;
use App\Jobs\SendMetaMessage;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Team-inbox writes. Each method locks the conversation row so concurrent
 * employees serialize on it, and records an immutable activity for audits.
 */
class ConversationWorkflow
{
    public function __construct(private TaskWorkflow $tasks) {}

    public function assign(Conversation $conversation, ?User $assignee, User $actor): Conversation
    {
        return DB::transaction(function () use ($conversation, $assignee, $actor): Conversation {
            $locked = $this->lock($conversation);
            $previousId = $locked->assigned_to_id;

            if ($previousId === $assignee?->id) {
                return $locked;
            }

            $locked->update([
                'assigned_to_id' => $assignee?->id,
                'assigned_at' => $assignee ? now() : null,
            ]);
            $this->activity($locked, $actor, 'assigned', null, [
                'from_user_id' => $previousId,
                'to_user_id' => $assignee?->id,
                'to_name' => $assignee?->name,
            ]);

            return $locked;
        });
    }

    public function setStatus(Conversation $conversation, string $status, User $actor): Conversation
    {
        return DB::transaction(function () use ($conversation, $status, $actor): Conversation {
            $locked = $this->lock($conversation);
            $previous = $locked->status;

            if ($previous === $status) {
                return $locked;
            }

            $locked->update([
                'status' => $status,
                'resolved_at' => $status === 'resolved' ? now() : null,
            ]);
            $this->activity($locked, $actor, 'status_changed', null, ['from' => $previous, 'to' => $status]);

            return $locked;
        });
    }

    public function addNote(Conversation $conversation, string $body, User $actor): ConversationActivity
    {
        return DB::transaction(fn (): ConversationActivity => $this->activity($this->lock($conversation), $actor, 'note', $body));
    }

    /**
     * Queue a reply. When another message was sent after the employee last
     * saw the thread, the reply is refused unless they confirm, so two people
     * do not answer the same customer twice without noticing.
     */
    public function queueReply(Conversation $conversation, string $body, User $actor, int $seenMessageId, bool $confirmed = false): Message
    {
        return DB::transaction(function () use ($conversation, $body, $actor, $seenMessageId, $confirmed): Message {
            $locked = $this->lock($conversation);

            $duplicate = $locked->messages()
                ->where('direction', 'outbound')
                ->where('sent_by_id', $actor->id)
                ->where('body', $body)
                ->where('created_at', '>=', now()->subMinute())
                ->first();
            if ($duplicate instanceof Message) {
                return $duplicate;
            }

            if (! $confirmed) {
                $newer = $locked->messages()
                    ->with('sentBy:id,name')
                    ->where('direction', 'outbound')
                    ->where('id', '>', $seenMessageId)
                    ->latest('id')
                    ->first();

                if ($newer instanceof Message) {
                    throw new ConcurrentReplyException($newer->sentBy?->name);
                }
            }

            $replyToCommentId = $locked->channel === 'facebook_comments'
                ? $locked->messages()
                    ->where('direction', 'inbound')
                    ->whereNotNull('external_id')
                    ->orderByDesc('sent_at')
                    ->orderByDesc('id')
                    ->value('external_id')
                : null;

            $message = Message::create([
                'conversation_id' => $locked->id,
                'sent_by_id' => $actor->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $body,
                'payload' => $replyToCommentId ? ['reply_to_comment_id' => $replyToCommentId] : null,
                'status' => 'queued',
                'sent_at' => now(),
            ]);

            $changes = ['last_message_at' => $message->sent_at];
            if ($locked->assigned_to_id === null) {
                $changes['assigned_to_id'] = $actor->id;
                $changes['assigned_at'] = now();
                $this->activity($locked, $actor, 'assigned', null, [
                    'from_user_id' => null,
                    'to_user_id' => $actor->id,
                    'to_name' => $actor->name,
                    'reason' => 'reply',
                ]);
            }
            $locked->update($changes);
            $this->activity($locked, $actor, 'reply_queued', null, [
                'message_id' => $message->id,
                'visibility' => $locked->replyVisibility(),
            ]);
            $this->markRead($locked, $actor);

            match ($locked->channel) {
                'facebook_comments' => SendMetaCommentReply::dispatch($message->id, $message->tenant_id)->afterCommit(),
                default => SendMetaMessage::dispatch($message->id, $message->tenant_id)->afterCommit(),
            };

            return $message;
        });
    }

    /** @param array<string, mixed> $data */
    public function createTask(Conversation $conversation, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($conversation, $data, $actor): Task {
            $locked = $this->lock($conversation);
            $task = $this->tasks->create([
                ...$data,
                'conversation_id' => $locked->id,
                'company_id' => $locked->company_id,
                'contact_id' => $locked->contact_id,
            ], $actor);
            $this->activity($locked, $actor, 'task_created', null, ['task_id' => $task->id, 'title' => $task->title]);

            return $task;
        });
    }

    public function linkContact(Conversation $conversation, Contact $contact, User $actor): Conversation
    {
        return DB::transaction(function () use ($conversation, $contact, $actor): Conversation {
            $locked = $this->lock($conversation);

            if ($locked->contact_id !== $contact->id) {
                $locked->update(['contact_id' => $contact->id]);
                $this->activity($locked, $actor, 'contact_linked', null, ['contact_id' => $contact->id, 'name' => $contact->full_name]);
            }

            return $locked;
        });
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        ConversationRead::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['last_read_at' => now()],
        );
    }

    /** @param array<string, mixed>|null $metadata */
    public function activity(Conversation $conversation, ?User $actor, string $type, ?string $body = null, ?array $metadata = null): ConversationActivity
    {
        return ConversationActivity::create([
            'conversation_id' => $conversation->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'body' => $body,
            'metadata' => $metadata,
        ]);
    }

    private function lock(Conversation $conversation): Conversation
    {
        return Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
    }
}
