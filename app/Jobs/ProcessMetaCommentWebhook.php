<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMetaCommentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public int $eventId,
        public int $tenantId,
    ) {}

    public function handle(CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $event = WebhookEvent::query()->with('integration')->findOrFail($this->eventId);

        if ($event->status === 'processed') {
            return;
        }

        try {
            $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1, 'error' => null]);
            $this->storeComment($event);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }

    private function storeComment(WebhookEvent $event): void
    {
        $payload = $event->payload;
        $commentId = (string) ($payload['comment_id'] ?? '');
        $parentId = (string) ($payload['parent_id'] ?? '');
        $postId = (string) ($payload['post_id'] ?? '');
        $senderId = (string) (data_get($payload, 'from.id') ?? $payload['sender_id'] ?? '');
        $senderName = trim((string) (data_get($payload, 'from.name') ?? $payload['sender_name'] ?? ''));
        $body = trim((string) ($payload['message'] ?? ''));
        $timestamp = (int) ($payload['created_time'] ?? 0);
        $sentAt = $timestamp > 0 ? CarbonImmutable::createFromTimestampUTC($timestamp) : now();

        DB::transaction(function () use ($event, $payload, $commentId, $parentId, $postId, $senderId, $senderName, $body, $sentAt): void {
            $locked = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->status === 'processed') {
                return;
            }

            $integration = $event->integration;
            $isRootComment = $parentId === '' || $parentId === $postId;
            $parentConversation = ! $isRootComment
                ? Message::query()
                    ->where('external_id', $parentId)
                    ->whereHas('conversation', fn ($query) => $query
                        ->where('integration_id', $integration->id)
                        ->where('channel', 'facebook_comments'))
                    ->with('conversation')
                    ->first()?->conversation
                : null;
            $threadId = $parentConversation?->external_thread_id ?: ($isRootComment ? $commentId : $parentId);
            $conversation = $parentConversation ?: Conversation::firstOrCreate([
                'integration_id' => $integration->id,
                'channel' => 'facebook_comments',
                'external_thread_id' => $threadId,
            ], [
                'company_id' => $integration->company_id,
                'external_participant_id' => $senderId !== '' ? $senderId : 'unknown',
                'participant_name' => $senderName !== '' ? $senderName : 'Facebook commenter',
                'status' => 'open',
                'last_message_at' => $sentAt,
            ]);

            Message::firstOrCreate([
                'conversation_id' => $conversation->id,
                'external_id' => $commentId,
            ], [
                'direction' => 'inbound',
                'type' => 'text',
                'body' => $body !== '' ? $body : 'Facebook comment without text',
                'payload' => [
                    ...$payload,
                    'post_id' => $postId,
                ],
                'status' => 'received',
                'sent_at' => $sentAt,
            ]);

            if ($conversation->last_message_at === null || $sentAt->greaterThan($conversation->last_message_at)) {
                $conversation->update(['last_message_at' => $sentAt]);
            }
            $locked->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        });
    }
}
