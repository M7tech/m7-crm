<x-layouts::app :title="$lead->title">
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('leads.index', ['pipeline' => $lead->pipeline_id]) }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to pipeline</a>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $lead->title }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $lead->company->name }} · {{ $lead->pipeline->name }}</p>
            </div>
            @can('update', $lead)
                <flux:button :href="route('leads.edit', $lead)" variant="primary" wire:navigate>Edit lead</flux:button>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Lead details</h2>
                </div>
                <dl class="grid sm:grid-cols-2">
                    @foreach ([
                        'Stage' => $lead->stage->name,
                        'Expected value' => $lead->formattedExpectedValue(),
                        'Assigned to' => $lead->assignedTo?->name ?? 'Unassigned',
                        'Contact' => $lead->contact?->full_name ?? '—',
                        'Source' => $lead->source ?: '—',
                        'Outcome date' => $lead->closed_at?->format('M j, Y H:i') ?? 'Open',
                        'Loss reason' => $lead->loss_reason ?: '—',
                        'Created' => $lead->created_at->format('M j, Y H:i'),
                    ] as $label => $value)
                        <div class="border-b border-zinc-100 px-5 py-4 sm:odd:border-e dark:border-zinc-800">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($lead->notes)
                    <div class="px-5 py-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Notes</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $lead->notes }}</p>
                    </div>
                @endif
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Activity</h2>
                <p class="mt-1 text-sm text-zinc-500">Permanent history for this opportunity.</p>
                <ol class="mt-5 grid gap-5">
                    @forelse ($lead->activities as $activity)
                        <li class="relative border-s-2 border-zinc-200 ps-4 dark:border-zinc-700">
                            <span class="absolute -start-[5px] top-1 size-2 rounded-full bg-emerald-500"></span>
                            <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $activity->description }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->created_at->format('M j, Y H:i') }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500">No activity recorded.</li>
                    @endforelse
                </ol>
            </aside>
        </div>
    </div>
</x-layouts::app>
