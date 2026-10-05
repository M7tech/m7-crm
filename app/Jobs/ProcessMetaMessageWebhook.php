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

class ProcessMetaMessageWebhook implements ShouldQueue
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
            $this->storeMessage($event);
        } catch (Throwable $exception) {
            $event->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw $exception;
        }
    }

    private function storeMessage(WebhookEvent $event): void
    {
        $payload = $event->payload;
        $senderId = (string) data_get($payload, 'sender.id', '');
        $externalId = (string) data_get($payload, 'message.mid', '');
        $body = data_get($payload, 'message.text');
        $timestamp = (int) ($payload['timestamp'] ?? 0);
        $sentAt = $timestamp > 0 ? CarbonImmutable::createFromTimestampUTC(intdiv($timestamp, 1000)) : now();

        DB::transaction(function () use ($event, $payload, $senderId, $externalId, $body, $sentAt): void {
            $locked = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->status === 'processed') {
                return;
            }

            $integration = $event->integration;
            $conversation = Conversation::firstOrCreate([
                'integration_id' => $integration->id,
                'channel' => 'facebook_messenger',
                'external_thread_id' => $senderId,
            ], [
                'company_id' => $integration->company_id,
                'external_participant_id' => $senderId,
                'participant_name' => 'Facebook contact '.substr($senderId, -6),
                'status' => 'open',
                'last_message_at' => $sentAt,
            ]);

            Message::firstOrCreate([
                'conversation_id' => $conversation->id,
                'external_id' => $externalId,
            ], [
                'direction' => 'inbound',
                'type' => is_string($body) && $body !== '' ? 'text' : 'unsupported',
                'body' => is_string($body) && $body !== '' ? $body : 'Unsupported Messenger attachment',
                'payload' => $payload,
                'status' => 'received',
                'sent_at' => $sentAt,
            ]);

            $conversation->update(['last_message_at' => $sentAt]);
            $locked->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        });
    }
}
