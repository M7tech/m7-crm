<?php

namespace App\Jobs;

use App\Jobs\Concerns\DeliversMetaReply;
use App\Models\Message;
use App\Services\MetaGraphClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaCommentReply implements ShouldQueue
{
    use DeliversMetaReply, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public int $messageId,
        public int $tenantId,
    ) {}

    protected function deliver(MetaGraphClient $client, Message $message): string
    {
        $targetId = (string) ($message->payload['reply_to_comment_id'] ?? $message->conversation->external_thread_id);

        return $client->replyToComment($message->conversation->integration, $targetId, (string) $message->body);
    }
}
