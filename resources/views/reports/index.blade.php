<x-layouts::app :title="__('Sales reports')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">Management</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Sales reports</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Conversion, pipeline value, ownership, and follow-up performance.</p>
            </div>
            <form method="GET" action="{{ route('reports.index') }}">
                <label for="period" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-zinc-500">Reporting period</label>
                <select id="period" name="period" onchange="this.form.submit()" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                    @foreach (['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'all' => 'All time'] as $value => $label)
                        <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Leads created', $leadCounts['total'], $periodLabel],
                ['Open leads', $leadCounts['open'], 'Still in progress'],
                ['Won leads', $leadCounts['won'], $leadCounts['lost'].' lost'],
                ['Win rate', number_format($winRate, 1).'%', 'Won ÷ decided leads'],
            ] as [$label, $value, $description])
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ is_numeric($value) ? number_format($value) : $value }}</p>
                    <p class="mt-2 text-sm text-zinc-500">{{ $description }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Pipeline value</h2>
                <p class="mt-1 text-sm text-zinc-500">Currencies remain separate to avoid misleading totals.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach (['open' => 'Open value', 'won' => 'Won value'] as $type => $label)
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/70">
                            <p class="text-sm font-medium text-zinc-500">{{ $label }}</p>
                            <p class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">{{ \App\Models\Lead::formatMinorValue($values[$type]['IQD'], 'IQD') }}</p>
                            <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ \App\Models\Lead::formatMinorValue($values[$type]['USD'], 'USD') }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Follow-up performance</h2>
                <p class="mt-1 text-sm text-zinc-500">Tasks created during {{ strtolower($periodLabel) }}.</p>
                <dl class="mt-5 grid grid-cols-2 gap-4">
                    @foreach ([
                        'Tasks created' => $tasks['created'],
                        'Completed' => $tasks['completed'],
                        'Completion rate' => number_format($tasks['completion_rate'], 1).'%',
                        'Currently overdue' => $tasks['overdue'],
                    ] as $label => $value)
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/70">
                            <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="font-semibold text-zinc-950 dark:text-white">Pipeline distribution</h2>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                @foreach ($pipelines as $pipeline)
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $pipeline['name'] }}</h3>
                            <span class="text-sm text-zinc-500">{{ $pipeline['total'] }} leads</span>
                        </div>
                        <div class="mt-3 grid gap-3">
                            @foreach ($pipeline['stages'] as $stage)
                                <div class="grid grid-cols-[6rem_minmax(0,1fr)_2rem] items-center gap-3 text-sm">
                                    <span class="truncate text-zinc-600 dark:text-zinc-400">{{ $stage['name'] }}</span>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div @class([
                                            'h-full rounded-full',
                                            'bg-emerald-500' => $stage['type'] === 'won',
                                            'bg-red-500' => $stage['type'] === 'lost',
                                            'bg-sky-500' => $stage['type'] === 'open',
                                        ]) style="width: {{ $stage['count'] > 0 ? max(4, $stage['width']) : 0 }}%"></div>
                                    </div>
                                    <span class="text-end font-medium text-zinc-900 dark:text-zinc-100">{{ $stage['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Team lead outcomes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/60">
                        <tr><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Leads</th><th class="px-5 py-3">Won</th><th class="px-5 py-3">Lost</th><th class="px-5 py-3">Win rate</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($assignees as $assignee)
                            <tr><td class="px-5 py-4 font-medium text-zinc-950 dark:text-white">{{ $assignee['name'] }}</td><td class="px-5 py-4">{{ $assignee['total'] }}</td><td class="px-5 py-4">{{ $assignee['won'] }}</td><td class="px-5 py-4">{{ $assignee['lost'] }}</td><td class="px-5 py-4">{{ number_format($assignee['win_rate'], 1) }}%</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-zinc-500">No leads in this reporting period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
