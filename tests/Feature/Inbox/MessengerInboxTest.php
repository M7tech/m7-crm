<?php

namespace Tests\Feature\Inbox;

use App\Enums\UserRole;
use App\Jobs\ProcessMetaMessageWebhook;
use App\Jobs\SendMetaMessage;
use App\Jobs\SyncMetaConversationMessages;
use App\Jobs\SyncMetaMessageHistory;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\Message;
use App\Models\PipelineStage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\MetaGraphClient;
use App\Services\PipelineProvisioner;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessengerInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_messenger_webhook_is_idempotent_and_creates_an_inbox_conversation(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $payload = ['object' => 'page', 'entry' => [[
            'id' => 'page-123',
            'messaging' => [[
                'sender' => ['id' => 'person-456'],
                'recipient' => ['id' => 'page-123'],
                'timestamp' => 1788530400000,
                'message' => ['mid' => 'message-789', 'text' => 'Is this product available?'],
            ]],
        ]]];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $server = ['HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'app-secret-value-long-enough'), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], $server, $raw)->assertOk();
        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], $server, $raw)->assertOk();

        $event = WebhookEvent::withoutGlobalScopes()->sole();
        $this->assertSame('messenger_message', $event->event_type);
        Queue::assertPushedTimes(ProcessMetaMessageWebhook::class, 1);

        (new ProcessMetaMessageWebhook($event->id, $event->tenant_id))->handle(app(CurrentTenant::class));

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $integration->tenant_id,
            'integration_id' => $integration->id,
            'external_participant_id' => 'person-456',
        ]);
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $integration->tenant_id,
            'external_id' => 'message-789',
            'direction' => 'inbound',
            'body' => 'Is this product available?',
        ]);
        $this->assertSame('processed', $event->fresh()->status);
    }

    public function test_inbox_is_tenant_isolated(): void
    {
        $integration = $this->integration();
        $conversation = $this->conversation($integration);
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $user = User::factory()->for($tenant)->create();
        $otherUser = User::factory()->for(Tenant::factory()->create())->create();

        $this->actingAs($user)->get(route('inbox.index'))->assertOk()->assertSee('Facebook contact');
        $this->actingAs($user)->get(route('inbox.show', $conversation))->assertOk()->assertSee('Hello from Facebook');
        $this->actingAs($otherUser)->get(route('inbox.index'))->assertOk()->assertDontSee('Hello from Facebook');
        $this->actingAs($otherUser)->get(route('inbox.show', $conversation))->assertNotFound();
        $this->actingAs($otherUser)->post(route('inbox.reply', $conversation), ['body' => 'Forbidden'])->assertNotFound();
    }

    public function test_authorized_reply_is_queued_and_sent_through_meta(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $conversation = $this->conversation($integration);
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($user)
            ->post(route('inbox.reply', $conversation), ['body' => ''])
            ->assertSessionHasErrors(['body']);

        $this->actingAs($user)
            ->post(route('inbox.reply', $conversation), ['body' => 'Yes, it is available.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inbox.show', $conversation));

        $message = Message::query()->where('direction', 'outbound')->sole();
        $this->assertSame('queued', $message->status);
        Queue::assertPushed(SendMetaMessage::class);

        Http::fake(['graph.facebook.com/*/page-123/messages' => Http::response(['message_id' => 'outbound-123'])]);
        (new SendMetaMessage($message->id, $tenant->id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('outbound-123', $message->external_id);
    }

    public function test_company_admin_can_queue_message_history_but_manager_cannot(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)
            ->post(route('integrations.meta.message-history', $integration))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('integrations.meta.message-history', $integration))
            ->assertRedirect(route('integrations.meta.index'))
            ->assertSessionHasNoErrors();

        Queue::assertPushed(SyncMetaMessageHistory::class, fn (SyncMetaMessageHistory $job): bool => $job->integrationId === $integration->id);
        $this->assertNotNull($integration->refresh()->settings['message_history_requested_at'] ?? null);
    }

    public function test_history_sync_imports_inbound_and_outbound_messages_idempotently(): void
    {
        Queue::fake();
        $integration = $this->integration();
        Http::fake([
            'graph.facebook.com/*/page-123/conversations*' => Http::response([
                'data' => [[
                    'id' => 'thread-123',
                    'participants' => ['data' => [
                        ['id' => 'page-123', 'name' => 'Quarter Bath Iraq'],
                        ['id' => 'person-456', 'name' => 'Historic Customer'],
                    ]],
                ]],
            ]),
        ]);

        (new SyncMetaMessageHistory($integration->id, $integration->tenant_id))
            ->handle(app(MetaGraphClient::class), app(CurrentTenant::class));

        Queue::assertPushed(SyncMetaConversationMessages::class, fn (SyncMetaConversationMessages $job): bool => $job->conversationId === 'thread-123'
            && $job->participantId === 'person-456'
            && $job->participantName === 'Historic Customer');

        Http::fake([
            'graph.facebook.com/*/thread-123*' => Http::response([
                'messages' => ['data' => [
                    [
                        'id' => 'historic-inbound',
                        'created_time' => '2026-08-01T10:00:00+0000',
                        'from' => ['id' => 'person-456'],
                        'to' => ['data' => [['id' => 'page-123']]],
                        'message' => 'Old customer message',
                    ],
                    [
                        'id' => 'historic-outbound',
                        'created_time' => '2026-08-01T10:05:00+0000',
                        'from' => ['id' => 'page-123'],
                        'to' => ['data' => [['id' => 'person-456']]],
                        'message' => 'Old Page reply',
                    ],
                ]],
            ]),
        ]);

        $job = new SyncMetaConversationMessages(
            $integration->id,
            $integration->tenant_id,
            'thread-123',
            'person-456',
            'Historic Customer',
        );
        $job->handle(app(MetaGraphClient::class), app(CurrentTenant::class));
        $job->handle(app(MetaGraphClient::class), app(CurrentTenant::class));

        $conversation = Conversation::query()->sole();
        $this->assertSame('Historic Customer', $conversation->participant_name);
        $this->assertSame($integration->company_id, $conversation->company_id);
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseHas('messages', ['external_id' => 'historic-inbound', 'direction' => 'inbound', 'status' => 'received']);
        $this->assertDatabaseHas('messages', ['external_id' => 'historic-outbound', 'direction' => 'outbound', 'status' => 'sent']);
    }

    private function integration(): Integration
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'open')->firstOrFail();
        $integration = new Integration([
            'public_id' => '29d6f4c4-31b5-43ce-a8cf-60c0f4f5bde2',
            'provider' => 'meta_lead_ads',
            'name' => 'Facebook Page',
            'status' => 'active',
            'credentials' => [
                'app_id' => '123456789',
                'app_secret' => 'app-secret-value-long-enough',
                'page_access_token' => 'page-token',
            ],
            'settings' => ['graph_version' => 'v26.0', 'verify_token' => 'verify-token'],
            'external_account_id' => 'page-123',
            'external_account_name' => 'Quarter Bath Iraq',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);
        $integration->tenant_id = $tenant->id;
        $integration->save();

        return $integration;
    }

    private function conversation(Integration $integration): Conversation
    {
        $conversation = new Conversation([
            'integration_id' => $integration->id,
            'company_id' => $integration->company_id,
            'channel' => 'facebook_messenger',
            'external_thread_id' => 'person-456',
            'external_participant_id' => 'person-456',
            'participant_name' => 'Facebook contact',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $conversation->tenant_id = $integration->tenant_id;
        $conversation->save();
        $message = new Message([
            'conversation_id' => $conversation->id,
            'external_id' => 'incoming-123',
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hello from Facebook',
            'status' => 'received',
            'sent_at' => now(),
        ]);
        $message->tenant_id = $integration->tenant_id;
        $message->save();

        return $conversation;
    }
}
