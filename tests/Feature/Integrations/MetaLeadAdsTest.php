<?php

namespace Tests\Feature\Integrations;

use App\Enums\UserRole;
use App\Jobs\ProcessMetaLeadWebhook;
use App\Models\Company;
use App\Models\Integration;
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

class MetaLeadAdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_an_encrypted_meta_connection(): void
    {
        [$tenant, $company, $pipeline, $stage] = $this->destination();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)->post(route('integrations.meta.store'), [
            'name' => 'Main Facebook Page',
            'app_id' => '123456789',
            'app_secret' => 'super-secret-value',
            'configuration_id' => '987654321012345',
            'graph_version' => 'v23.0',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'assigned_to_id' => $admin->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('integrations.meta.index'));

        $integration = Integration::withoutGlobalScopes()->sole();
        $this->assertSame($tenant->id, $integration->tenant_id);
        $this->assertSame('super-secret-value', $integration->credentials['app_secret']);
        $this->assertStringNotContainsString('super-secret-value', (string) $integration->getRawOriginal('credentials'));
        $this->assertSame('987654321012345', $integration->settings['configuration_id']);
    }

    public function test_non_admin_cannot_create_a_meta_connection(): void
    {
        [$tenant, $company, $pipeline, $stage] = $this->destination();
        $manager = User::factory()->for($tenant)->create(['role' => UserRole::SalesManager]);

        $this->actingAs($manager)->post(route('integrations.meta.store'), [
            'name' => 'Forbidden', 'app_id' => '1', 'app_secret' => 'secret', 'graph_version' => 'v23.0',
            'company_id' => $company->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
        ])->assertForbidden();
    }

    public function test_meta_connection_rejects_an_email_as_app_id_and_a_reused_configuration_id(): void
    {
        [$tenant, $company, $pipeline, $stage] = $this->destination();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)->post(route('integrations.meta.store'), [
            'name' => 'Invalid Meta connection',
            'app_id' => 'mohammed@m7tech.info',
            'app_secret' => 'long-enough-app-secret',
            'configuration_id' => 'mohammed@m7tech.info',
            'graph_version' => 'v26.0',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ])->assertSessionHasErrors(['app_id', 'configuration_id']);

        $this->actingAs($admin)->post(route('integrations.meta.store'), [
            'name' => 'Repeated Meta ID',
            'app_id' => '1765800834750343',
            'app_secret' => 'long-enough-app-secret',
            'configuration_id' => '1765800834750343',
            'graph_version' => 'v26.0',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ])->assertSessionHasErrors(['configuration_id']);

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_company_admin_sees_the_meta_setup_guide_and_support_scope(): void
    {
        [$tenant] = $this->destination();
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->get(route('integrations.meta.index'))
            ->assertOk()
            ->assertSee('Facebook &amp; Instagram setup guide', false)
            ->assertSee('Facebook and Instagram Instant Form leads')
            ->assertSee('https://developers.facebook.com/apps/', false)
            ->assertSee('https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving/', false);
    }

    public function test_company_admin_can_save_the_business_login_configuration_id(): void
    {
        $integration = $this->integration();
        $settings = $integration->settings;
        unset($settings['configuration_id']);
        $integration->update(['settings' => $settings]);
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->put(route('integrations.meta.configuration', $integration), [
                'configuration_id' => '987654321012345',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('integrations.meta.index'));

        $this->assertSame('987654321012345', $integration->refresh()->settings['configuration_id']);
    }

    public function test_another_tenant_cannot_update_a_meta_configuration(): void
    {
        $integration = $this->integration();
        $otherAdmin = User::factory()->for(Tenant::factory()->create())->companyAdmin()->create();

        $this->actingAs($otherAdmin)
            ->put(route('integrations.meta.configuration', $integration), [
                'configuration_id' => '111111111111111',
            ])
            ->assertNotFound();

        $this->assertSame('987654321012345', $integration->refresh()->settings['configuration_id']);
    }

    public function test_connect_facebook_uses_the_business_login_configuration(): void
    {
        $integration = $this->integration();
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $response = $this->actingAs($admin)->get(route('integrations.meta.redirect', $integration));

        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('987654321012345', $query['config_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('true', $query['override_default_response_type']);
        $this->assertSame('rerequest', $query['auth_type']);
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function test_page_subscription_failure_returns_to_integrations_with_actionable_error(): void
    {
        $integration = $this->integration();
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        Http::fake([
            'graph.facebook.com/*/page-789/subscribed_apps' => Http::response([
                'error' => ['message' => 'Missing permission'],
            ], 400),
        ]);

        $this->actingAs($admin)
            ->withSession(['meta_page_selection.valid-selection' => [
                'integration_id' => $integration->id,
                'user_id' => $admin->id,
                'user_token' => 'long-user-token',
                'pages' => [[
                    'id' => 'page-789',
                    'name' => 'AtlasPlast',
                    'access_token' => 'page-access-token',
                ]],
            ]])
            ->post(route('integrations.meta.page', $integration), [
                'selection' => 'valid-selection',
                'page_id' => 'page-789',
            ])
            ->assertRedirect(route('integrations.meta.index'))
            ->assertSessionHasErrors(['meta']);

        $this->assertNull($integration->refresh()->external_account_id);
    }

    public function test_company_admin_can_delete_an_obsolete_meta_connection(): void
    {
        $integration = $this->integration();
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();

        $this->actingAs($admin)
            ->delete(route('integrations.meta.destroy', $integration))
            ->assertRedirect(route('integrations.meta.index'));

        $this->assertDatabaseMissing('integrations', ['id' => $integration->id]);
    }

    public function test_another_tenant_cannot_delete_a_meta_connection(): void
    {
        $integration = $this->integration();
        $otherAdmin = User::factory()->for(Tenant::factory()->create())->companyAdmin()->create();

        $this->actingAs($otherAdmin)
            ->delete(route('integrations.meta.destroy', $integration))
            ->assertNotFound();

        $this->assertDatabaseHas('integrations', ['id' => $integration->id]);
    }

    public function test_company_admin_can_authorize_meta_and_choose_a_page_in_the_app(): void
    {
        $integration = $this->integration();
        $tenant = Tenant::query()->findOrFail($integration->tenant_id);
        $admin = User::factory()->for($tenant)->companyAdmin()->create();
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-user-token'])
                ->push(['access_token' => 'long-user-token']),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [[
                'id' => 'page-789',
                'name' => 'M7 Sales Page',
                'access_token' => 'page-access-token',
                'tasks' => ['ADVERTISE', 'MANAGE'],
            ]]]),
            'graph.facebook.com/*/page-789/subscribed_apps' => Http::response(['success' => true]),
        ]);

        $this->actingAs($admin)
            ->withSession(['meta_oauth_state.valid-state' => $integration->public_id])
            ->get(route('integrations.meta.callback', ['state' => 'valid-state', 'code' => 'authorization-code']))
            ->assertOk()
            ->assertSee('M7 Sales Page');

        $this->actingAs($admin)
            ->withSession(['meta_page_selection.valid-selection' => [
                'integration_id' => $integration->id,
                'user_id' => $admin->id,
                'user_token' => 'long-user-token',
                'pages' => [[
                    'id' => 'page-789',
                    'name' => 'M7 Sales Page',
                    'access_token' => 'page-access-token',
                ]],
            ]])
            ->post(route('integrations.meta.page', $integration), [
                'selection' => 'valid-selection',
                'page_id' => 'page-789',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('integrations.meta.index'));

        $integration->refresh();
        $this->assertSame('active', $integration->status);
        $this->assertSame('page-789', $integration->external_account_id);
        $this->assertSame('page-access-token', $integration->credentials['page_access_token']);
        $this->assertStringNotContainsString('page-access-token', (string) $integration->getRawOriginal('credentials'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/page-789/subscribed_apps'));
    }

    public function test_webhook_verification_and_signed_delivery_are_idempotent(): void
    {
        Queue::fake();
        $integration = $this->integration();

        // PHP normalizes Meta's dotted query-string keys before Laravel sees them.
        $this->get(route('webhooks.meta.verify', $integration->public_id).'?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $integration->settings['verify_token'],
            'hub_challenge' => 'challenge-123',
        ]))->assertOk()->assertSeeText('challenge-123');

        $payload = ['object' => 'page', 'entry' => [[
            'id' => $integration->external_account_id,
            'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => 'lead-100', 'page_id' => $integration->external_account_id]]],
        ]]];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $server = ['HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, $integration->credentials['app_secret']), 'CONTENT_TYPE' => 'application/json'];

        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], $server, $raw)->assertOk();
        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], $server, $raw)->assertOk();

        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushedTimes(ProcessMetaLeadWebhook::class, 1);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $integration = $this->integration();

        $this->call('POST', route('webhooks.meta.receive', $integration->public_id), [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertUnauthorized();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_queued_webhook_creates_contact_lead_and_activity(): void
    {
        $integration = $this->integration();
        $event = new WebhookEvent([
            'integration_id' => $integration->id,
            'provider' => 'meta_lead_ads',
            'external_id' => 'lead-200',
            'event_type' => 'leadgen',
            'payload' => ['leadgen_id' => 'lead-200'],
            'status' => 'pending',
        ]);
        $event->tenant_id = $integration->tenant_id;
        $event->save();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'lead-200',
                'campaign_name' => 'Autumn campaign',
                'ad_name' => 'CRM ad',
                'field_data' => [
                    ['name' => 'full_name', 'values' => ['Sara Ahmed']],
                    ['name' => 'email', 'values' => ['sara@example.com']],
                    ['name' => 'phone_number', 'values' => ['07500000000']],
                ],
            ]),
        ]);

        (new ProcessMetaLeadWebhook($event->id, $event->tenant_id))->handle(app(MetaGraphClient::class), app(CurrentTenant::class));

        $this->assertDatabaseHas('contacts', ['tenant_id' => $integration->tenant_id, 'email' => 'sara@example.com']);
        $this->assertDatabaseHas('leads', ['tenant_id' => $integration->tenant_id, 'title' => 'Meta lead: Sara Ahmed', 'source' => 'Meta Lead Ads']);
        $this->assertDatabaseHas('lead_activities', ['tenant_id' => $integration->tenant_id, 'type' => 'created']);
        $this->assertSame('processed', $event->fresh()->status);
    }

    /** @return array{Tenant, Company, \App\Models\Pipeline, PipelineStage} */
    private function destination(): array
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->for($tenant)->create();
        $pipeline = app(PipelineProvisioner::class)->createDefault($tenant);
        $stage = PipelineStage::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->where('type', 'open')->firstOrFail();

        return [$tenant, $company, $pipeline, $stage];
    }

    private function integration(): Integration
    {
        [$tenant, $company, $pipeline, $stage] = $this->destination();
        $integration = new Integration([
            'public_id' => '19d6f4c4-31b5-43ce-a8cf-60c0f4f5bde1',
            'provider' => 'meta_lead_ads',
            'name' => 'Test Meta',
            'status' => 'active',
            'credentials' => ['app_id' => '123', 'app_secret' => 'app-secret', 'page_access_token' => 'page-token'],
            'settings' => [
                'graph_version' => 'v26.0',
                'verify_token' => 'verify-token',
                'configuration_id' => '987654321012345',
            ],
            'external_account_id' => 'page-123',
            'external_account_name' => 'Test Page',
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
        ]);
        $integration->tenant_id = $tenant->id;
        $integration->save();

        return $integration;
    }
}
