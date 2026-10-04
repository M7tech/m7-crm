<x-layouts::app :title="__('Agents')">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-600 dark:text-violet-400">{{ __('Attribution') }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Sales agents') }}</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Agents are statistical labels only. They cannot log in, view customers, or own CRM tasks.') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __('Attribution list') }}</h2>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($agents as $agent)
                        <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-zinc-950 dark:text-white">{{ $agent->name }}</p>
                                    @if ($agent->code)<span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $agent->code }}</span>@endif
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $agent->status === 'active',
                                        'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $agent->status !== 'active',
                                    ])>{{ __($agent->status === 'active' ? 'Active' : 'Inactive') }}</span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-500">{{ __(':leads attributed leads · :won won', ['leads' => $agent->leads_count, 'won' => $agent->won_leads_count]) }}</p>
                            </div>
                            <form method="POST" action="{{ route('agents.status', $agent) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="{{ $agent->status === 'active' ? 'inactive' : 'active' }}">
                                <flux:button type="submit" variant="ghost">{{ __($agent->status === 'active' ? 'Deactivate' : 'Activate') }}</flux:button>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-zinc-500">{{ __('No statistical agents yet.') }}</div>
                    @endforelse
                </div>
            </section>

            <aside class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">{{ __('Add agent') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('Use a name and optional code that managers recognize in reports.') }}</p>
                <form method="POST" action="{{ route('agents.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="name" :label="__('Agent name')" :value="old('name')" maxlength="160" required />
                    <flux:input name="code" :label="__('Agent code (optional)')" :value="old('code')" maxlength="50" placeholder="AG-001" />
                    <flux:button type="submit" variant="primary">{{ __('Add agent') }}</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
