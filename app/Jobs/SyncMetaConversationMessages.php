<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\MetaGraphClient;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SyncMetaConversationMessages implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public int $integrationId,
        public int $tenantId,
        public string $conversationId,
        public ?string $participantId = null,
        public ?string $participantName = null,
        public ?string $after = null,
    ) {}

    public function handle(MetaGraphClient $client, CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $integration = Integration::query()->findOrFail($this->integrationId);

        if ($integration->status !== 'active' || blank($integration->external_account_id) || blank($integration->credentials['page_access_token'] ?? null)) {
            return;
        }

        $page = $client->conversationMessages($integration, $this->conversationId, $this->after);
        $participantId = $this->participantId ?: $this->participantFromMessages($page['data'], (string) $integration->external_account_id);

        if ($participantId === null) {
            return;
        }

        DB::transaction(function () use ($integration, $page, $participantId): void {
            $conversation = Conversation::firstOrCreate([
                'integration_id' => $integration->id,
                'channel' => 'facebook_messenger',
                'external_thread_id' => $participantId,
            ], [
                'company_id' => $integration->company_id,
                'external_participant_id' => $participantId,
                'participant_name' => $this->participantName ?: 'Facebook contact '.substr($participantId, -6),
                'status' => 'open',
            ]);

            if ($this->participantName !== null && $conversation->participant_name !== $this->participantName) {
                $conversation->update(['participant_name' => $this->participantName]);
            }

            $latest = $conversation->last_message_at?->toImmutable();
            foreach ($page['data'] as $item) {
                $externalId = $item['id'] ?? null;
                if (! is_string($externalId) || $externalId === '') {
                    continue;
                }

                $sentAt = is_string($item['created_time'] ?? null)
                    ? CarbonImmutable::parse($item['created_time'])->utc()
                    : now()->toImmutable();
                $body = is_string($item['message'] ?? null) && $item['message'] !== ''
                    ? $item['message']
                    : 'Unsupported Messenger message';
                $type = is_string($item['message'] ?? null) && $item['message'] !== '' ? 'text' : 'unsupported';
                $direction = (string) data_get($item, 'from.id') === (string) $integration->external_account_id
                    ? 'outbound'
                    : 'inbound';

                Message::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'external_id' => $externalId,
                ], [
                    'direction' => $direction,
                    'type' => $type,
                    'body' => $body,
                    'payload' => $item,
                    'status' => $direction === 'outbound' ? 'sent' : 'received',
                    'sent_at' => $sentAt,
                ]);

                if ($latest === null || $sentAt->greaterThan($latest)) {
                    $latest = $sentAt;
                }
            }

            if ($latest !== null && ($conversation->last_message_at === null || $latest->greaterThan($conversation->last_message_at))) {
                $conversation->update(['last_message_at' => $latest]);
            }
        });

        if ($page['after'] !== null) {
            self::dispatch(
                $integration->id,
                $integration->tenant_id,
                $this->conversationId,
                $participantId,
                $this->participantName,
                $page['after'],
            );
        }
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function participantFromMessages(array $messages, string $pageId): ?string
    {
        foreach ($messages as $message) {
            $fromId = data_get($message, 'from.id');
            if (is_string($fromId) && $fromId !== '' && $fromId !== $pageId) {
                return $fromId;
            }

            foreach (data_get($message, 'to.data', []) as $recipient) {
                if (is_array($recipient) && is_string($recipient['id'] ?? null) && $recipient['id'] !== $pageId) {
                    return $recipient['id'];
                }
            }
        }

        return null;
    }
}
