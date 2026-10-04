<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Tenant;
use App\Services\MetaGraphClient;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendMetaCommentReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public int $messageId,
        public int $tenantId,
    ) {}

    public function handle(MetaGraphClient $client, CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $message = Message::query()->with('conversation.integration')->findOrFail($this->messageId);

        if ($message->status === 'sent') {
            return;
        }

        try {
            $message->update(['status' => 'sending']);
            $targetId = (string) ($message->payload['reply_to_comment_id'] ?? $message->conversation->external_thread_id);
            $externalId = $client->replyToComment(
                $message->conversation->integration,
                $targetId,
                (string) $message->body,
            );
            $message->update(['external_id' => $externalId, 'status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $message->update(['status' => 'failed']);
            throw $exception;
        }
    }
}
