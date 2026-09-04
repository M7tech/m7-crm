<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\Tenant;
use App\Services\MetaGraphClient;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMetaMessageHistory implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public int $integrationId,
        public int $tenantId,
        public ?string $after = null,
    ) {}

    public function handle(MetaGraphClient $client, CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $integration = Integration::query()->findOrFail($this->integrationId);

        if ($integration->status !== 'active' || blank($integration->external_account_id) || blank($integration->credentials['page_access_token'] ?? null)) {
            return;
        }

        $page = $client->conversations($integration, $this->after);

        foreach ($page['data'] as $conversation) {
            $conversationId = $conversation['id'] ?? null;
            if (! is_string($conversationId) || $conversationId === '') {
                continue;
            }

            $participant = collect(data_get($conversation, 'participants.data', []))
                ->first(fn (mixed $candidate): bool => is_array($candidate)
                    && is_string($candidate['id'] ?? null)
                    && $candidate['id'] !== (string) $integration->external_account_id);

            SyncMetaConversationMessages::dispatch(
                $integration->id,
                $integration->tenant_id,
                $conversationId,
                is_array($participant) ? (string) $participant['id'] : null,
                is_array($participant) && is_string($participant['name'] ?? null) ? $participant['name'] : null,
            );
        }

        if ($page['after'] !== null) {
            self::dispatch($integration->id, $integration->tenant_id, $page['after']);
        }
    }
}
