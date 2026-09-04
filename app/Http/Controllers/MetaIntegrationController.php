<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMetaIntegrationRequest;
use App\Http\Requests\UpdateMetaIntegrationConfigurationRequest;
use App\Http\Requests\UpdateMetaIntegrationRoutingRequest;
use App\Jobs\SyncMetaMessageHistory;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MetaGraphClient;
use App\Services\PlanEntitlements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetaIntegrationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Integration::class);

        return view('integrations.meta.index', [
            'integrations' => Integration::query()->where('provider', 'meta_lead_ads')->with(['company', 'pipeline', 'stage'])->latest()->get(),
            ...$this->formData(),
        ]);
    }

    public function store(StoreMetaIntegrationRequest $request, PlanEntitlements $plans): RedirectResponse
    {
        $data = $request->validated();
        $integration = DB::transaction(function () use ($request, $data, $plans): Integration {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($request->user()->tenant_id);
            $plans->assertCapacity($tenant, 'meta_connections', 'name');
            $publicId = (string) Str::uuid();
            $webhook = $this->sharedWebhook($data['app_id'], $publicId);

            return Integration::create([
                'public_id' => $publicId,
                'provider' => 'meta_lead_ads',
                'name' => $data['name'],
                'status' => 'draft',
                'credentials' => ['app_id' => $data['app_id'], 'app_secret' => $data['app_secret']],
                'settings' => [
                    'graph_version' => $data['graph_version'],
                    'verify_token' => $webhook['verify_token'],
                    'webhook_id' => $webhook['webhook_id'],
                    'configuration_id' => $data['configuration_id'],
                ],
                'company_id' => $data['company_id'],
                'pipeline_id' => $data['pipeline_id'],
                'stage_id' => $data['stage_id'],
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
            ]);
        });

        return to_route('integrations.meta.index')->with('status', $integration->name.' was created. Configure its webhook, then connect Facebook.');
    }

    public function updateRouting(UpdateMetaIntegrationRoutingRequest $request, string $integration): RedirectResponse
    {
        $connection = $request->connection();
        $data = $request->validated();
        $connection->update([
            'company_id' => $data['company_id'],
            'pipeline_id' => $data['pipeline_id'],
            'stage_id' => $data['stage_id'],
            'assigned_to_id' => $data['assigned_to_id'] ?? null,
        ]);

        return to_route('integrations.meta.index')->with('status', 'Lead destination updated for '.($connection->external_account_name ?: $connection->name).'.');
    }

    public function updateConfiguration(UpdateMetaIntegrationConfigurationRequest $request, string $integration): RedirectResponse
    {
        $connection = $request->connection();
        $connection->update([
            'settings' => [
                ...$connection->settings,
                'configuration_id' => $request->validated('configuration_id'),
            ],
        ]);

        return to_route('integrations.meta.index')->with('status', 'Meta Business Login Configuration ID saved.');
    }

    public function redirect(string $integration, Request $request): RedirectResponse
    {
        $integration = $this->connection($integration);
        $this->authorize('update', $integration);
        abort_unless($integration->provider === 'meta_lead_ads', 404);
        $configurationId = $integration->settings['configuration_id'] ?? null;
        if (! is_string($configurationId) || $configurationId === '') {
            return to_route('integrations.meta.index')->withErrors([
                'meta' => 'Add the Facebook Login for Business Configuration ID before connecting Facebook.',
            ]);
        }

        $state = Str::random(64);
        $request->session()->put('meta_oauth_state.'.$state, $integration->public_id);
        $query = http_build_query([
            'client_id' => $integration->credentials['app_id'],
            'redirect_uri' => route('integrations.meta.callback'),
            'state' => $state,
            'config_id' => $configurationId,
            'response_type' => 'code',
            'override_default_response_type' => 'true',
            'auth_type' => 'rerequest',
        ]);

        return redirect()->away('https://www.facebook.com/'.$integration->settings['graph_version'].'/dialog/oauth?'.$query);
    }

    public function callback(Request $request, MetaGraphClient $client): View|RedirectResponse
    {
        $request->validate(['state' => ['required', 'string'], 'code' => ['required_without:error', 'string']]);
        $publicId = $request->session()->pull('meta_oauth_state.'.$request->string('state')->value());
        abort_unless(is_string($publicId), 403, 'The Meta authorization state expired or is invalid.');
        $integration = Integration::query()->where('public_id', $publicId)->firstOrFail();
        $this->authorize('update', $integration);

        if ($request->filled('error')) {
            return to_route('integrations.meta.index')->withErrors(['meta' => 'Facebook authorization was cancelled.']);
        }

        try {
            $token = $client->exchangeCode($integration, $request->string('code')->value(), route('integrations.meta.callback'));
            $userToken = $client->longLivedToken($integration, (string) $token['access_token']);
            $pages = $client->pages($integration, $userToken);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['meta' => 'Meta could not complete authorization. Check the App ID, App Secret, redirect URI, and permissions.']);
        }

        $selection = Str::random(48);
        $request->session()->put('meta_page_selection.'.$selection, [
            'integration_id' => $integration->id,
            'user_id' => $request->user()->id,
            'user_token' => $userToken,
            'pages' => $pages,
        ]);

        return view('integrations.meta.pages', compact('integration', 'pages', 'selection'));
    }

    public function selectPage(Request $request, string $integration, MetaGraphClient $client): RedirectResponse
    {
        $integration = $this->connection($integration);
        $this->authorize('update', $integration);
        $data = $request->validate(['selection' => ['required', 'string'], 'page_id' => ['required', 'string']]);
        $selection = $request->session()->pull('meta_page_selection.'.$data['selection']);
        abort_unless(
            is_array($selection)
            && ($selection['integration_id'] ?? null) === $integration->id
            && ($selection['user_id'] ?? null) === $request->user()->id
            && is_string($selection['user_token'] ?? null)
            && is_array($selection['pages'] ?? null),
            403,
        );
        $page = collect($selection['pages'])->first(
            fn (mixed $candidate): bool => is_array($candidate) && ($candidate['id'] ?? null) === $data['page_id'],
        );
        abort_unless(
            is_array($page)
            && is_string($page['id'] ?? null)
            && is_string($page['name'] ?? null)
            && is_string($page['access_token'] ?? null),
            422,
            'The selected Page is unavailable.',
        );

        try {
            $messengerEnabled = $client->subscribePage($integration, (string) $page['id'], (string) $page['access_token']);
        } catch (\Throwable) {
            return to_route('integrations.meta.index')->withErrors([
                'meta' => 'Meta returned the Page but refused its leadgen webhook subscription. Add pages_manage_metadata and leads_retrieval to the Business Login configuration, save it, then reconnect Facebook.',
            ]);
        }

        $integration->update([
            'status' => 'active',
            'external_account_id' => (string) $page['id'],
            'external_account_name' => (string) $page['name'],
            'credentials' => [
                ...$integration->credentials,
                'user_access_token' => $selection['user_token'],
                'page_access_token' => $page['access_token'],
            ],
            'connected_at' => now(),
        ]);

        $status = $page['name'].' is connected to Meta Lead Ads.';
        if (! $messengerEnabled) {
            $status .= ' Add pages_messaging to the Business Login configuration and reconnect to enable the inbox.';
        }

        return to_route('integrations.meta.index')->with('status', $status);
    }

    public function destroy(string $integration): RedirectResponse
    {
        $integration = $this->connection($integration);
        $this->authorize('delete', $integration);
        $name = $integration->name;
        $integration->delete();

        return to_route('integrations.meta.index')->with('status', $name.' was deleted.');
    }

    public function syncMessageHistory(string $integration): RedirectResponse
    {
        $connection = $this->connection($integration);
        $this->authorize('update', $connection);
        abort_unless($connection->provider === 'meta_lead_ads', 404);

        if ($connection->status !== 'active' || blank($connection->external_account_id) || blank($connection->credentials['page_access_token'] ?? null)) {
            return to_route('integrations.meta.index')->withErrors([
                'meta' => 'Connect the Facebook Page before importing its message history.',
            ]);
        }

        $connection->update([
            'settings' => [
                ...$connection->settings,
                'message_history_requested_at' => now()->toIso8601String(),
            ],
        ]);
        SyncMetaMessageHistory::dispatch($connection->id, $connection->tenant_id);

        return to_route('integrations.meta.index')->with('status', 'Messenger history import queued for '.$connection->external_account_name.'. Messages will appear progressively in the inbox.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $tenantId = request()->user()->tenant_id;

        return [
            'companies' => Company::query()->orderBy('name')->get(),
            'pipelines' => Pipeline::query()->with('stages')->orderByDesc('is_default')->get(),
            'members' => User::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get(),
        ];
    }

    private function connection(string $publicId): Integration
    {
        return Integration::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** @return array{webhook_id: string, verify_token: string} */
    private function sharedWebhook(string $appId, string $fallbackId): array
    {
        $existing = Integration::query()
            ->where('provider', 'meta_lead_ads')
            ->get()
            ->first(fn (Integration $candidate): bool => (string) ($candidate->credentials['app_id'] ?? '') === $appId);

        return [
            'webhook_id' => (string) ($existing?->settings['webhook_id'] ?? $existing?->public_id ?? $fallbackId),
            'verify_token' => (string) ($existing?->settings['verify_token'] ?? Str::random(48)),
        ];
    }
}
