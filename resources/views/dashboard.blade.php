<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">{{ $tenant?->name ?? 'M7 CRM' }}</p>
                    @if ($planName)<span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200">{{ $planName }} plan</span>@endif
                </div>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Sales workspace</h1>
                <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">Your customer, pipeline, and follow-up overview.</p>
            </div>
            <div class="flex gap-2">
                @can('viewReports')
                    <flux:button :href="route('reports.index')" wire:navigate>View reports</flux:button>
                @endcan
                <flux:button :href="route('companies.index')" icon="plus" variant="primary" wire:navigate>Add company</flux:button>
            </div>
        </div>

        @if ($onboarding && $onboarding['completed'] < $onboarding['total'])
            <section class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-900 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 border-b border-violet-100 bg-violet-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-violet-900 dark:bg-violet-950/30">
                    <div>
                        <h2 class="font-semibold text-violet-950 dark:text-violet-100">Workspace setup</h2>
                        <p class="mt-1 text-sm text-violet-800 dark:text-violet-200">{{ $onboarding['completed'] }} of {{ $onboarding['total'] }} steps complete</p>
                    </div>
                    <div class="h-2 w-full max-w-52 overflow-hidden rounded-full bg-violet-100 dark:bg-violet-900" role="progressbar" aria-valuenow="{{ $onboarding['completed'] }}" aria-valuemin="0" aria-valuemax="{{ $onboarding['total'] }}">
                        <div class="h-full rounded-full bg-violet-600" style="width: {{ ($onboarding['completed'] / $onboarding['total']) * 100 }}%"></div>
                    </div>
                </div>
                <div class="grid gap-px bg-zinc-200 sm:grid-cols-2 xl:grid-cols-5 dark:bg-zinc-700">
                    @foreach ($onboarding['steps'] as $step)
                        <a href="{{ $step['url'] }}" class="group bg-white p-4 transition hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800" wire:navigate>
                            <span class="flex size-7 items-center justify-center rounded-full {{ $step['complete'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' }}" aria-hidden="true">{{ $step['complete'] ? '✓' : '○' }}</span>
                            <h3 class="mt-3 text-sm font-medium text-zinc-950 group-hover:text-violet-700 dark:text-white dark:group-hover:text-violet-300">{{ $step['label'] }}</h3>
                            <p class="mt-1 text-xs leading-5 text-zinc-500">{{ $step['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($planUsage)
            <details class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <summary class="cursor-pointer px-5 py-4 font-semibold text-zinc-950 dark:text-white">{{ $planName }} plan usage</summary>
                <div class="grid gap-4 border-t border-zinc-200 p-5 sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-700">
                    @foreach ($planUsage as $usage)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $usage['label'] }}</span>
                                <span class="font-medium text-zinc-950 dark:text-white">{{ number_format($usage['used']) }} / {{ $usage['limit'] === null ? 'Unlimited' : number_format($usage['limit']) }}</span>
                            </div>
                            @if ($usage['limit'] !== null)
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                    <div class="h-full rounded-full {{ $usage['used'] >= $usage['limit'] ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min(100, ($usage['used'] / max(1, $usage['limit'])) * 100) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Companies</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($companyCount) }}</p>
                <p class="mt-2 text-sm text-zinc-500">Customer business accounts</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Open leads</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($openLeadCount) }}</p>
                <p class="mt-2 text-sm text-zinc-500">Active sales opportunities</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500">Due today</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ number_format($dueTodayCount) }}</p>
                <p class="mt-2 text-sm text-zinc-500">Tasks requiring attention</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900 dark:bg-red-950/40">
                <p class="text-sm font-medium text-red-700 dark:text-red-300">Overdue tasks</p>
                <p class="mt-3 text-3xl font-semibold text-red-950 dark:text-red-100">{{ number_format($overdueTaskCount) }}</p>
                <p class="mt-2 text-sm text-red-700/80 dark:text-red-300/80">Follow-ups past their due time</p>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div>
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Recently added companies</h2>
                    <p class="mt-1 text-sm text-zinc-500">Your latest customer accounts</p>
                </div>
                <flux:link :href="route('companies.index')" wire:navigate>View all</flux:link>
            </div>

            @forelse ($recentCompanies as $company)
                <div class="flex items-center justify-between gap-4 border-b border-zinc-100 px-5 py-4 last:border-0 dark:border-zinc-800">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-zinc-950 dark:text-white">{{ $company->name }}</p>
                        <p class="mt-0.5 truncate text-sm text-zinc-500">{{ $company->city ?: 'City not added' }} · {{ $company->phone ?: 'No phone' }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-zinc-500">{{ $company->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-medium text-zinc-950 dark:text-white">No customer companies yet</p>
                    <p class="mt-1 text-sm text-zinc-500">Add the first company to begin building the CRM.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-layouts::app>
