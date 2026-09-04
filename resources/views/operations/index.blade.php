<x-layouts::app :title="__('Operations')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Platform administration</p>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Operations</h1>
                <p class="mt-1 text-sm text-zinc-500">Live service heartbeats, queue pressure, and recent processing failures.</p>
            </div>
            <a href="{{ route('operations.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-100 dark:hover:bg-zinc-800" wire:navigate>
                Refresh status
            </a>
        </div>

        <section aria-labelledby="service-health-heading">
            <div class="mb-3 flex items-center justify-between gap-4">
                <h2 id="service-health-heading" class="font-semibold text-zinc-950 dark:text-white">Service health</h2>
                <p class="text-end text-xs text-zinc-500">Heartbeats are healthy when seen within 3 minutes.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($health as $service)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-medium text-zinc-950 dark:text-white">{{ $service['name'] }}</h3>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' => $service['status'] === 'healthy',
                                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' => $service['status'] === 'stale',
                                'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' => in_array($service['status'], ['missing', 'unavailable'], true),
                            ])>{{ $service['status'] }}</span>
                        </div>
                        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ $service['detail'] }}</p>
                        <p class="mt-2 text-xs text-zinc-500">
                            {{ $service['checked_at'] ? 'Checked '.$service['checked_at']->diffForHumans() : 'Run the scheduled heartbeat after deployment.' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="platform-counts-heading">
            <h2 id="platform-counts-heading" class="mb-3 font-semibold text-zinc-950 dark:text-white">Platform overview</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                @foreach ([
                    ['Active workspaces', $counts['activeTenants'], false],
                    ['Suspended', $counts['suspendedTenants'], $counts['suspendedTenants'] > 0],
                    ['Workspace users', $counts['users'], false],
                    ['Queued jobs', $counts['queuedJobs'], $counts['queuedJobs'] !== null && $counts['queuedJobs'] > 25],
                    ['Failed jobs', $counts['failedJobs'], $counts['failedJobs'] > 0],
                    ['Failed webhooks', $counts['failedWebhooks'], $counts['failedWebhooks'] > 0],
                    ['Failed automations', $counts['failedAutomations'], $counts['failedAutomations'] > 0],
                ] as [$label, $value, $attention])
                    <div @class([
                        'rounded-2xl border bg-white p-4 shadow-sm dark:bg-zinc-900',
                        'border-red-200 dark:border-red-900' => $attention,
                        'border-zinc-200 dark:border-zinc-700' => ! $attention,
                    ])>
                        <p class="text-xs font-medium text-zinc-500">{{ $label }}</p>
                        <p @class([
                            'mt-2 text-2xl font-semibold',
                            'text-red-700 dark:text-red-400' => $attention,
                            'text-zinc-950 dark:text-white' => ! $attention,
                        ])>{{ $value === null ? '—' : number_format($value) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        @php
            $failureGroups = [
                ['title' => 'Queue failures', 'empty' => 'No failed queue jobs.', 'items' => $failedJobs, 'columns' => ['name' => 'Job', 'queue' => 'Connection / queue']],
                ['title' => 'Webhook failures', 'empty' => 'No failed webhook events.', 'items' => $failedWebhooks, 'columns' => ['tenant' => 'Workspace', 'source' => 'Integration', 'type' => 'Event']],
                ['title' => 'Automation failures', 'empty' => 'No failed automation runs.', 'items' => $failedAutomations, 'columns' => ['tenant' => 'Workspace', 'rule' => 'Rule']],
            ];
        @endphp

        <div class="grid gap-6">
            @foreach ($failureGroups as $group)
                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $group['title'] }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60">
                                <tr>
                                    @foreach ($group['columns'] as $label)
                                        <th class="px-5 py-3">{{ $label }}</th>
                                    @endforeach
                                    <th class="px-5 py-3">Error</th>
                                    <th class="px-5 py-3">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($group['items'] as $item)
                                    <tr>
                                        @foreach ($group['columns'] as $key => $label)
                                            <td class="whitespace-nowrap px-5 py-4 font-medium text-zinc-950 dark:text-white">{{ $item[$key] }}</td>
                                        @endforeach
                                        <td class="min-w-72 px-5 py-4 text-zinc-600 dark:text-zinc-400">{{ $item['error'] }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-zinc-500">{{ \Illuminate\Support\Carbon::parse($item['failed_at'])->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($group['columns']) + 2 }}" class="px-5 py-10 text-center text-zinc-500">{{ $group['empty'] }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts::app>
