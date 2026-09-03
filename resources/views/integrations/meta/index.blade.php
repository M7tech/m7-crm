<x-layouts::app :title="__('Meta Lead Ads')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-400">Integrations</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Meta Lead Ads</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Connect a Facebook Page and send new Instant Form submissions into your sales pipeline.</p>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
            Create the Meta developer app once, then manage the connection here. After adding its App ID and Secret, copy the displayed OAuth redirect URI and Page webhook details to Meta, then click <strong>Connect Facebook</strong> to choose the Page and CRM destination.
        </div>

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
                            <flux:button :href="route('integrations.meta.redirect', $integration)" variant="primary">{{ $integration->status === 'active' ? 'Reconnect Facebook' : 'Connect Facebook' }}</flux:button>
                        </div>

                        <div class="mt-5 grid gap-4 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-zinc-800/70">
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
                    <flux:input name="graph_version" :label="__('Graph API version')" :value="old('graph_version', 'v23.0')" required />
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
