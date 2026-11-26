<?php

namespace Tests\Feature\Inbox;

use App\Enums\UserRole;
use App\Jobs\ProcessMetaMessageWebhook;
use App\Jobs\SendMetaMessage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\Integration;
use App\Models\Message;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\MetaGraphClient;
use App\Services\PipelineProvisioner;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamInboxTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'app-secret-value-long-enough';

    public function test_incoming_message_is_assigned_answered_and_followed_up_on_the_task_board(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $tenant = $integration->tenant;
        $agent = User::factory()->for($tenant)->create(['name' => 'Sara Agent']);
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager, 'name' => 'Mona Manager']);
        $outsider = User::factory()->for(Tenant::factory()->create())->create();

        // 1. An incoming Messenger message is delivered twice by Meta.
        $this->deliverMessage($integration, 'mid-1', 'Do you deliver to Duhok?');
        $this->deliverMessage($integration, 'mid-1', 'Do you deliver to Duhok?');
        $event = WebhookEvent::withoutGlobalScopes()->sole();
        Queue::assertPushedTimes(ProcessMetaMessageWebhook::class, 1);
        (new ProcessMetaMessageWebhook($event->id, $event->tenant_id))->handle(app(CurrentTenant::class));
        (new ProcessMetaMessageWebhook($event->id, $event->tenant_id))->handle(app(CurrentTenant::class));

        $conversation = Conversation::withoutGlobalScopes()->sole();
        $this->assertSame(1, Message::withoutGlobalScopes()->count());
        $this->assertNotNull($conversation->last_inbound_at);

        // 2. It appears, unread and unassigned, in the right team's inbox only.
        $this->actingAs($agent)->get(route('inbox.index', ['assignee' => 'unassigned', 'unread' => 1]))
            ->assertOk()->assertSee('Do you deliver to Duhok?');
        $this->actingAs($agent)->getJson(route('inbox.index'))
            ->assertOk()->assertJsonPath('html', fn (string $html) => str_contains($html, 'Do you deliver to Duhok?'));
        $this->actingAs($outsider)->get(route('inbox.index'))->assertOk()->assertDontSee('Do you deliver to Duhok?');

        // 3. A manager assigns it to the agent.
        $this->actingAs($manager)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $agent->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('inbox.show', $conversation));
        $this->assertSame($agent->id, $conversation->fresh()->assigned_to_id);

        // Opening the conversation marks it read for that employee only.
        $this->actingAs($agent)->get(route('inbox.show', $conversation))
            ->assertOk()->assertSee('PRIVATE')->assertSee('Sara Agent');
        $this->actingAs($agent)->get(route('inbox.index', ['unread' => 1]))->assertDontSee('Do you deliver to Duhok?');
        $this->actingAs($manager)->get(route('inbox.index', ['unread' => 1]))->assertSee('Do you deliver to Duhok?');

        // 4. The agent sends a supported private reply.
        $this->actingAs($agent)->post(route('inbox.reply', $conversation), [
            'body' => 'Yes, delivery to Duhok takes two days.',
            'seen_message_id' => (int) Message::query()->max('id'),
        ])->assertSessionHasNoErrors();
        $reply = Message::query()->where('direction', 'outbound')->sole();
        $this->assertSame($agent->id, $reply->sent_by_id);
        Queue::assertPushed(SendMetaMessage::class);
        Http::fake(['graph.facebook.com/*/page-123/messages' => Http::response(['message_id' => 'out-1'])]);
        (new SendMetaMessage($reply->id, $tenant->id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));
        $this->assertSame('sent', $reply->fresh()->status);

        // 5. The agent creates a follow-up task from the conversation.
        $this->actingAs($agent)->post(route('inbox.tasks.store', $conversation), [
            'title' => 'Confirm Duhok delivery date',
            'assigned_to_id' => $agent->id,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'priority' => 'high',
        ])->assertSessionHasNoErrors();
        $task = Task::query()->sole();
        $this->assertSame($conversation->id, $task->conversation_id);
        $this->assertSame($conversation->company_id, $task->company_id);

        // 6. The same task appears in My Tasks, the team list, and the board.
        $this->actingAs($agent)->get(route('tasks.index', ['scope' => 'mine']))->assertOk()->assertSee('Confirm Duhok delivery date');
        $this->actingAs($manager)->get(route('tasks.index', ['scope' => 'team']))->assertOk()->assertSee('Confirm Duhok delivery date');
        $this->actingAs($agent)->get(route('tasks.board'))->assertOk()->assertSee('Confirm Duhok delivery date');
        $this->actingAs($outsider)->get(route('tasks.board'))->assertOk()->assertDontSee('Confirm Duhok delivery date');

        // Moving the card on the board changes the one shared record.
        $this->actingAs($agent)->putJson(route('tasks.status.update', $task), ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('status', 'in_progress');
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->actingAs($agent)->get(route('tasks.index', ['scope' => 'mine', 'filter' => 'pending']))->assertSee('Confirm Duhok delivery date');

        // The audit trail records assignment, reply, and task creation.
        $types = ConversationActivity::query()->pluck('type')->all();
        $this->assertContains('assigned', $types);
        $this->assertContains('reply_queued', $types);
        $this->assertContains('task_created', $types);
    }

    public function test_salesperson_cannot_take_over_or_reply_to_a_colleagues_conversation(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $tenant = $integration->tenant;
        $owner = User::factory()->for($tenant)->create();
        $colleague = User::factory()->for($tenant)->create();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);
        $conversation = $this->conversation($integration);

        // A salesperson may claim an unassigned conversation, but not hand it to someone else.
        $this->actingAs($colleague)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $owner->id])
            ->assertSessionHasErrors('assigned_to_id');
        $this->actingAs($owner)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $owner->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($colleague)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $colleague->id])->assertForbidden();
        $this->actingAs($colleague)->put(route('inbox.status', $conversation), ['status' => 'resolved'])->assertForbidden();
        $this->actingAs($colleague)->post(route('inbox.reply', $conversation), ['body' => 'Hi', 'seen_message_id' => 0])->assertForbidden();
        $this->actingAs($colleague)->get(route('inbox.show', $conversation))->assertOk()->assertDontSee('data-reply-composer', false);

        // Teammates can still leave internal notes, and managers can reassign.
        $this->actingAs($colleague)->post(route('inbox.notes.store', $conversation), ['note' => 'Customer called the shop too.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $colleague->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, $conversation->fresh()->assigned_to_id);

        // The owner can hand off their own conversation.
        $conversation->update(['assigned_to_id' => $owner->id]);
        $this->actingAs($owner)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $colleague->id])
            ->assertSessionHasNoErrors();
    }

    public function test_internal_notes_never_reach_the_customer_and_are_validated(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $user = User::factory()->for($integration->tenant)->create();
        $conversation = $this->conversation($integration);

        $this->actingAs($user)->post(route('inbox.notes.store', $conversation), ['note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($user)->post(route('inbox.notes.store', $conversation), ['note' => str_repeat('a', 3001)])->assertSessionHasErrors('note');
        $this->actingAs($user)->post(route('inbox.notes.store', $conversation), ['note' => 'VIP <b>customer</b>'])->assertSessionHasNoErrors();

        $this->assertSame(0, Message::query()->where('direction', 'outbound')->count());
        Queue::assertNothingPushed();
        $this->actingAs($user)->get(route('inbox.show', $conversation))
            ->assertSee('Internal note')
            ->assertSee('VIP &lt;b&gt;customer&lt;/b&gt;', false);

        $this->actingAs($user)->put(route('inbox.status', $conversation), ['status' => 'archived'])->assertSessionHasErrors('status');
    }

    public function test_inbox_writes_and_filters_are_tenant_isolated(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $conversation = $this->conversation($integration);
        $otherIntegration = $this->integration('page-999');
        $outsider = User::factory()->for($otherIntegration->tenant)->create(['role' => UserRole::CompanyAdmin]);
        $insider = User::factory()->for($integration->tenant)->create(['role' => UserRole::CompanyAdmin]);
        $foreignContact = Contact::factory()->for($otherIntegration->tenant)->create(['company_id' => $otherIntegration->company_id]);

        $this->actingAs($outsider)->get(route('inbox.show', $conversation))->assertNotFound();
        $this->actingAs($outsider)->getJson(route('inbox.updates', [$conversation, 'after' => 0]))->assertNotFound();
        $this->actingAs($outsider)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $outsider->id])->assertNotFound();
        $this->actingAs($outsider)->put(route('inbox.status', $conversation), ['status' => 'resolved'])->assertNotFound();
        $this->actingAs($outsider)->post(route('inbox.notes.store', $conversation), ['note' => 'x'])->assertNotFound();
        $this->actingAs($outsider)->post(route('inbox.tasks.store', $conversation), [])->assertNotFound();

        // A foreign assignee, account filter, or contact cannot cross the tenant boundary.
        $this->actingAs($insider)->put(route('inbox.assign', $conversation), ['assigned_to_id' => $outsider->id])->assertSessionHasErrors('assigned_to_id');
        $this->actingAs($insider)->get(route('inbox.index', ['account' => $otherIntegration->id]))->assertDontSee('Hello from Facebook');
        $this->actingAs($insider)->put(route('inbox.contact.link', $conversation), ['contact_id' => $foreignContact->id])->assertSessionHasErrors('contact_id');
        $this->actingAs($insider)->post(route('inbox.tasks.store', $conversation), [
            'title' => 'Cross-tenant',
            'assigned_to_id' => $outsider->id,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'priority' => 'normal',
        ])->assertSessionHasErrors('assigned_to_id');

        $this->assertNull($conversation->fresh()->assigned_to_id);
        $this->assertSame(0, Task::withoutGlobalScopes()->count());
    }

    public function test_reply_guard_preserves_the_draft_when_a_colleague_answered_first(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $manager = User::factory()->for($integration->tenant)->create(['role' => UserRole::SalesManager, 'name' => 'Ali Manager']);
        $other = User::factory()->for($integration->tenant)->create(['role' => UserRole::SalesManager]);
        $conversation = $this->conversation($integration);
        $seen = (int) Message::query()->max('id');

        $this->actingAs($manager)->post(route('inbox.reply', $conversation), ['body' => 'First answer', 'seen_message_id' => $seen])->assertSessionHasNoErrors();
        $this->actingAs($other)->post(route('inbox.reply', $conversation), ['body' => 'Second answer', 'seen_message_id' => $seen])
            ->assertSessionHasErrors('body')
            ->assertSessionHasInput('body', 'Second answer');
        $this->assertSame(1, Message::query()->where('direction', 'outbound')->count());

        $this->actingAs($other)->post(route('inbox.reply', $conversation), ['body' => 'Second answer', 'seen_message_id' => $seen, 'confirm_send' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Message::query()->where('direction', 'outbound')->count());

        // Double-submitting the same reply does not send it twice.
        $latest = (int) Message::query()->max('id');
        $this->actingAs($other)->post(route('inbox.reply', $conversation), ['body' => 'Second answer', 'seen_message_id' => $latest])->assertSessionHasNoErrors();
        $this->assertSame(2, Message::query()->where('direction', 'outbound')->count());
    }

    public function test_failed_sends_are_classified_and_keep_the_reply_text(): void
    {
        $integration = $this->integration();
        $tenant = $integration->tenant;
        $conversation = $this->conversation($integration);
        app(CurrentTenant::class)->set($tenant);

        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => ['code' => 10, 'error_subcode' => 2018278, 'message' => 'outside window']], 400)
            ->push(['error' => ['code' => 613, 'message' => 'rate limit']], 400)
            ->push(['error' => ['code' => 190, 'message' => 'expired token']], 400)]);

        $windowClosed = $this->outbound($conversation, 'Late reply');
        (new SendMetaMessage($windowClosed->id, $tenant->id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));
        $windowClosed->refresh();
        $this->assertSame('failed', $windowClosed->status);
        $this->assertSame('Late reply', $windowClosed->body);
        $this->assertStringContainsString('24 hours', (string) $windowClosed->error);
        $this->assertDatabaseHas('conversation_activities', ['conversation_id' => $conversation->id, 'type' => 'reply_failed']);

        $rateLimited = $this->outbound($conversation, 'Busy reply');
        (new SendMetaMessage($rateLimited->id, $tenant->id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));
        $this->assertSame('queued', $rateLimited->fresh()->status);

        $expired = $this->outbound($conversation, 'Token reply');
        (new SendMetaMessage($expired->id, $tenant->id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));
        $this->assertSame('failed', $expired->fresh()->status);
        $this->assertSame('reconnect_required', $integration->fresh()->status);

        $user = User::factory()->for($tenant)->create();
        $this->actingAs($user)->get(route('inbox.show', $conversation))
            ->assertSee('Copy back to the composer')
            ->assertSee('Replies are paused');
        $this->actingAs($user)->getJson(route('inbox.updates', [$conversation, 'after' => 0]))
            ->assertOk()
            ->assertJsonFragment(['id' => $windowClosed->id, 'status' => 'failed']);
    }

    public function test_new_customer_message_reopens_a_resolved_conversation(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $user = User::factory()->for($integration->tenant)->create();
        $conversation = $this->conversation($integration);
        $this->actingAs($user)->put(route('inbox.status', $conversation), ['status' => 'resolved'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $conversation->fresh()->status);
        $this->actingAs($user)->post(route('inbox.reply', $conversation), ['body' => 'x', 'seen_message_id' => 999])->assertForbidden();

        $this->travel(5)->minutes();
        $this->deliverMessage($integration, 'mid-2', 'One more question');
        $event = WebhookEvent::withoutGlobalScopes()->sole();
        (new ProcessMetaMessageWebhook($event->id, $event->tenant_id))->handle(app(CurrentTenant::class));

        $this->assertSame('open', $conversation->fresh()->status);
        $this->assertNull($conversation->fresh()->resolved_at);
    }

    public function test_disconnect_keeps_conversations_revokes_tokens_and_is_admin_only(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $conversation = $this->conversation($integration);
        $admin = User::factory()->for($integration->tenant)->create(['role' => UserRole::CompanyAdmin]);
        $manager = User::factory()->for($integration->tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)->post(route('integrations.meta.disconnect', $integration))->assertForbidden();
        $this->actingAs($admin)->post(route('integrations.meta.disconnect', $integration))->assertRedirect(route('integrations.meta.index'));

        $integration->refresh();
        $this->assertSame('disconnected', $integration->status);
        $this->assertArrayNotHasKey('page_access_token', $integration->credentials);
        $this->assertSame($admin->id, $integration->disconnected_by_id);
        $this->assertNotNull(Conversation::query()->find($conversation->id));
        $this->actingAs($admin)->post(route('inbox.reply', $conversation), ['body' => 'x', 'seen_message_id' => 999])->assertForbidden();

        // Webhooks for a disconnected Page are acknowledged but not ingested.
        $this->deliverMessage($integration, 'mid-3', 'Anyone there?');
        $this->assertSame(0, WebhookEvent::withoutGlobalScopes()->count());
    }

    public function test_link_existing_contact_and_show_conversation_on_the_contact_record(): void
    {
        Queue::fake();
        $integration = $this->integration();
        $user = User::factory()->for($integration->tenant)->create();
        $conversation = $this->conversation($integration);
        $contact = Contact::factory()->for($integration->tenant)->create(['company_id' => $integration->company_id, 'first_name' => 'Karwan']);

        $this->actingAs($user)->get(route('inbox.show', [$conversation, 'contact_q' => 'karw']))->assertSee('Karwan');
        $this->actingAs($user)->put(route('inbox.contact.link', $conversation), ['contact_id' => $contact->id])->assertSessionHasNoErrors();
        $this->assertSame($contact->id, $conversation->fresh()->contact_id);

        $this->actingAs($user)->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertSee('Social conversations')
            ->assertSee(route('inbox.show', $conversation), false);
    }

    public function test_board_status_changes_respect_task_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $colleague = User::factory()->for($tenant)->create();
        app(CurrentTenant::class)->set($tenant);
        $task = Task::factory()->for($tenant)->create(['assigned_to_id' => $owner->id, 'created_by_id' => $owner->id]);

        $this->actingAs($colleague)->putJson(route('tasks.status.update', $task), ['status' => 'completed'])->assertForbidden();
        $this->actingAs($colleague)->get(route('tasks.board'))->assertDontSee($task->title);
        $this->actingAs($owner)->putJson(route('tasks.status.update', $task), ['status' => 'blocked'])->assertUnprocessable();
        $this->actingAs($owner)->putJson(route('tasks.status.update', $task), ['status' => 'completed'])->assertOk();
        $this->assertNotNull($task->fresh()->completed_at);
    }

    private function deliverMessage(Integration $integration, string $mid, string $text): void
    {
        $raw = json_encode(['object' => 'page', 'entry' => [[
            'id' => $integration->external_account_id,
            'messaging' => [[
                'sender' => ['id' => 'person-456'],
                'recipient' => ['id' => $integration->external_account_id],
                'timestamp' => now()->getTimestampMs(),
                'message' => ['mid' => $mid, 'text' => $text],
            ]],
        ]]], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();
    }

    private function integration(string $pageId = 'page-123'): Integration
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'open')->firstOrFail();
        $integration = new Integration([
            'public_id' => (string) Str::uuid(),
            'provider' => 'meta_lead_ads',
            'name' => 'Facebook Page',
            'status' => 'active',
            'credentials' => ['app_id' => '123456789', 'app_secret' => self::SECRET, 'page_access_token' => 'page-token'],
            'settings' => ['graph_version' => 'v26.0', 'verify_token' => 'verify-token', 'messenger_enabled' => true, 'comments_enabled' => true],
            'external_account_id' => $pageId,
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
            'last_inbound_at' => now(),
        ]);
        $conversation->tenant_id = $integration->tenant_id;
        $conversation->save();

        $message = new Message([
            'conversation_id' => $conversation->id,
            'external_id' => 'inbound-'.$conversation->id,
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

    private function outbound(Conversation $conversation, string $body): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $body,
            'status' => 'queued',
            'sent_at' => now(),
        ]);
    }
}
