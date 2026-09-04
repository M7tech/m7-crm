<x-layouts::app :title="__('Meta Lead Ads')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Integrations</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Meta Lead Ads</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Connect Facebook and Instagram Instant Forms to your sales pipeline.</p>
            </div>
            <a href="#meta-setup-guide" class="inline-flex w-fit items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                Setup guide
                <span aria-hidden="true">↓</span>
            </a>
        </div>

        <section id="meta-setup-guide" class="scroll-mt-6 overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm dark:border-blue-900 dark:bg-zinc-900">
            <div class="border-b border-blue-100 bg-blue-50 px-5 py-4 dark:border-blue-900 dark:bg-blue-950/40">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-blue-950 dark:text-blue-100">Facebook &amp; Instagram setup guide</h2>
                        <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">Follow these steps once for each Meta developer app.</p>
                    </div>
                    <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Open Meta Apps <span aria-hidden="true">↗</span>
                    </a>
                </div>
            </div>

            <ol class="grid gap-px bg-zinc-200 sm:grid-cols-2 xl:grid-cols-4 dark:bg-zinc-700">
                <li class="bg-white p-5 dark:bg-zinc-900">
                    <span class="flex size-7 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">1</span>
                    <h3 class="mt-3 font-medium text-zinc-950 dark:text-white">Create the Meta app</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">In Meta for Developers, create or open your Business app and copy its App ID and App Secret.</p>
                </li>
                <li class="bg-white p-5 dark:bg-zinc-900">
                    <span class="flex size-7 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">2</span>
                    <h3 class="mt-3 font-medium text-zinc-950 dark:text-white">Create the CRM connection</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Enter the credentials and destination below. The CRM then displays your exact OAuth and webhook values.</p>
                </li>
                <li class="bg-white p-5 dark:bg-zinc-900">
                    <span class="flex size-7 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">3</span>
                    <h3 class="mt-3 font-medium text-zinc-950 dark:text-white">Configure Meta</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Add the shown OAuth URI, create a Facebook Login for Business configuration, and save its Configuration ID here. Then configure the <strong>Page</strong> webhook and subscribe to <code>leadgen</code>.</p>
                </li>
                <li class="bg-white p-5 dark:bg-zinc-900">
                    <span class="flex size-7 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">4</span>
                    <h3 class="mt-3 font-medium text-zinc-950 dark:text-white">Connect your Page</h3>
                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Click <strong>Connect Facebook</strong>, approve access, and select the Page linked to your Facebook or Instagram lead forms.</p>
                </li>
            </ol>

            <div class="flex flex-col gap-3 border-t border-zinc-200 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700">
                <p class="text-zinc-600 dark:text-zinc-400"><strong class="text-zinc-900 dark:text-zinc-100">Currently supported:</strong> Facebook and Instagram Instant Form leads. Messenger and Instagram direct messages are not connected yet.</p>
                <a href="https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving/" target="_blank" rel="noopener noreferrer" class="shrink-0 font-medium text-blue-600 hover:underline dark:text-blue-400">Meta Lead Ads help ↗</a>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
            <div class="grid gap-5">
                @forelse ($integrations as $integration)
                    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $integration->name }}</h2>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $integration->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">{{ ucfirst($integration->status) }}</span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-500">{{ $integration->external_account_name ?: 'No Facebook Page selected' }} → {{ $integration->company->name }} / {{ $integration->stage->name }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if (filled($integration->settings['configuration_id'] ?? null))
                                    <flux:button :href="route('integrations.meta.redirect', $integration)" variant="primary">{{ $integration->status === 'active' ? 'Reconnect Facebook' : 'Connect Facebook' }}</flux:button>
                                @else
                                    <a href="#configuration-{{ $integration->public_id }}" class="inline-flex items-center justify-center rounded-lg bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:hover:bg-amber-900">Add Configuration ID</a>
                                @endif
                                @can('delete', $integration)
                                    <form method="POST" action="{{ route('integrations.meta.destroy', $integration) }}" onsubmit="return confirm('Delete this Meta connection and its webhook history?')">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="danger">Delete</flux:button>
                                    </form>
                                @endcan
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/70">
                            <form id="configuration-{{ $integration->public_id }}" method="POST" action="{{ route('integrations.meta.configuration', $integration) }}" class="grid gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label for="configuration_id_{{ $integration->public_id }}" class="font-medium text-amber-950 dark:text-amber-100">Facebook Login for Business Configuration ID</label>
                                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">Meta App → Facebook Login for Business → Configurations</p>
                                    <input id="configuration_id_{{ $integration->public_id }}" name="configuration_id" inputmode="numeric" pattern="[0-9]+" value="{{ old('configuration_id', $integration->settings['configuration_id'] ?? '') }}" required class="mt-2 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-zinc-950 dark:border-amber-800 dark:bg-zinc-900 dark:text-white">
                                </div>
                                <flux:button type="submit" variant="primary">Save ID</flux:button>
                            </form>
                            <div>
                                <p class="font-medium text-zinc-700 dark:text-zinc-300">Webhook callback URL</p>
                                <code class="mt-1 block break-all text-xs text-zinc-600 dark:text-zinc-400">{{ route('webhooks.meta.verify', $integration->public_id) }}</code>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-700 dark:text-zinc-300">Webhook verify token</p>
                                <code class="mt-1 block break-all text-xs text-zinc-600 dark:text-zinc-400">{{ $integration->settings['verify_token'] }}</code>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-700 dark:text-zinc-300">Valid OAuth redirect URI</p>
                                <code class="mt-1 block break-all text-xs text-zinc-600 dark:text-zinc-400">{{ route('integrations.meta.callback') }}</code>
                            </div>
                        </div>
                    </section>
                @empty
                    <section class="rounded-2xl border border-dashed border-zinc-300 bg-white px-5 py-14 text-center dark:border-zinc-700 dark:bg-zinc-900">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">No Meta connection yet</h2>
                        <p class="mt-1 text-sm text-zinc-500">Add the Meta app details using the form.</p>
                    </section>
                @endforelse
            </div>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Add Meta app</h2>
                <p class="mt-1 text-sm text-zinc-500">Credentials are encrypted before database storage.</p>
                <form method="POST" action="{{ route('integrations.meta.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="name" :label="__('Connection name')" :value="old('name', 'Facebook Lead Ads')" required />
                    <flux:input name="app_id" :label="__('Meta App ID')" :value="old('app_id')" required />
                    <flux:input name="app_secret" type="password" :label="__('Meta App Secret')" required />
                    <flux:input name="configuration_id" :label="__('Business Login Configuration ID')" :description="__('Create this under Meta → Facebook Login for Business → Configurations.')" :value="old('configuration_id')" inputmode="numeric" required />
                    <flux:input name="graph_version" :label="__('Graph API version')" :value="old('graph_version', 'v26.0')" required />
                    <div>
                        <label for="company_id" class="mb-2 block text-sm font-medium">Destination company</label>
                        <select id="company_id" name="company_id" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($companies as $company)<option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>{{ $company->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="pipeline_id" class="mb-2 block text-sm font-medium">Pipeline</label>
                        <select id="pipeline_id" name="pipeline_id" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($pipelines as $pipeline)<option value="{{ $pipeline->id }}" @selected((int) old('pipeline_id') === $pipeline->id)>{{ $pipeline->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="stage_id" class="mb-2 block text-sm font-medium">Starting stage</label>
                        <select id="stage_id" name="stage_id" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($pipelines as $pipeline)<optgroup label="{{ $pipeline->name }}">@foreach ($pipeline->stages->where('type', 'open') as $stage)<option value="{{ $stage->id }}" @selected((int) old('stage_id') === $stage->id)>{{ $stage->name }}</option>@endforeach</optgroup>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="assigned_to_id" class="mb-2 block text-sm font-medium">Default assignee</label>
                        <select id="assigned_to_id" name="assigned_to_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            <option value="">Unassigned</option>
                            @foreach ($members as $member)<option value="{{ $member->id }}" @selected((int) old('assigned_to_id') === $member->id)>{{ $member->name }}</option>@endforeach
                        </select>
                    </div>
                    <flux:button type="submit" variant="primary" class="w-full">Create connection</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
