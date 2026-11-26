<?php

namespace App\Jobs\Concerns;

use App\Exceptions\MetaApiException;
use App\Models\ConversationActivity;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\MetaGraphClient;
use App\Support\CurrentTenant;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Shared delivery for queued Meta replies. Rate limits and provider outages
 * are released back to the queue; permanent failures (expired token, closed
 * messaging window, missing permission) stop immediately and are shown to
 * employees with the original draft still available.
 */
trait DeliversMetaReply
{
    abstract protected function deliver(MetaGraphClient $client, Message $message): string;

    public function handle(MetaGraphClient $client, CurrentTenant $currentTenant): void
    {
        $currentTenant->set(Tenant::query()->findOrFail($this->tenantId));
        $message = Message::query()->with('conversation.integration')->findOrFail($this->messageId);

        if ($message->status === 'sent') {
            return;
        }

        $integration = $message->conversation->integration;
        if ($integration->status !== 'active' || blank($integration->credentials['page_access_token'] ?? null)) {
            $this->markFailed($message, __('The Facebook Page is disconnected. A company admin must reconnect it before replies can be sent.'), 'disconnected');

            return;
        }

        try {
            $message->update(['status' => 'sending']);
            $externalId = $this->deliver($client, $message);
            $message->update(['external_id' => $externalId, 'status' => 'sent', 'error' => null, 'sent_at' => now()]);
        } catch (RequestException $exception) {
            $error = MetaApiException::fromRequest($exception);

            if ($error->isRetryable() && $this->attempts() < $this->tries) {
                $message->update(['status' => 'queued', 'error' => $error->getMessage()]);
                $this->release($error->retryAfterSeconds ?? ($this->backoff[$this->attempts() - 1] ?? 60));

                return;
            }

            if ($error->kind === 'expired') {
                $integration->update(['status' => 'reconnect_required']);
            }

            $this->markFailed($message, $error->getMessage(), $error->kind);
        } catch (Throwable $exception) {
            $message->update(['status' => 'failed', 'error' => __('The reply could not be delivered.')]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant) {
            return;
        }

        app(CurrentTenant::class)->set($tenant);
        $message = Message::query()->find($this->messageId);

        if ($message instanceof Message && $message->status !== 'sent') {
            $message->update(['status' => 'failed', 'error' => $message->error ?? __('The reply could not be delivered.')]);
        }
    }

    private function markFailed(Message $message, string $reason, string $kind): void
    {
        $message->update(['status' => 'failed', 'error' => mb_substr($reason, 0, 500)]);
        ConversationActivity::create([
            'conversation_id' => $message->conversation_id,
            'actor_id' => null,
            'type' => 'reply_failed',
            'metadata' => ['message_id' => $message->id, 'kind' => $kind],
        ]);
    }
}
