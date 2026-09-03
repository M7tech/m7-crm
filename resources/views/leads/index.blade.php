<x-layouts::app :title="__('Sales pipeline')">
    <div class="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">Sales</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Pipeline</h1>
                <p class="mt-1 text-base text-zinc-600 dark:text-zinc-400">Move opportunities through each stage and keep their history intact.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                @if ($pipelines->count() > 1)
                    <form method="GET" action="{{ route('leads.index') }}">
                        <label for="pipeline" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-zinc-500">Pipeline</label>
                        <select id="pipeline" name="pipeline" onchange="this.form.submit()" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($pipelines as $option)
                                <option value="{{ $option->id }}" @selected($option->is($pipeline))>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @can('create', \App\Models\Pipeline::class)
                    <flux:button :href="route('pipelines.index')" variant="ghost" icon="cog-6-tooth" wire:navigate>Configure</flux:button>
                @endcan
                <flux:button :href="route('leads.create')" variant="primary" icon="plus" wire:navigate>Add lead</flux:button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="overflow-x-auto pb-4">
            <div class="grid min-w-max auto-cols-[19rem] grid-flow-col gap-4">
                @foreach ($pipeline->stages as $stage)
                    @php
                        $stageClasses = match ($stage->type) {
                            'won' => 'bg-emerald-500',
                            'lost' => 'bg-red-500',
                            default => match ($stage->color) {
                                'sky' => 'bg-sky-500',
                                'violet' => 'bg-violet-500',
                                'amber' => 'bg-amber-500',
                                'emerald' => 'bg-emerald-500',
                                'red' => 'bg-red-500',
                                default => 'bg-zinc-500',
                            },
                        };
                    @endphp
                    <section class="flex max-h-[calc(100vh-15rem)] flex-col rounded-2xl border border-zinc-200 bg-zinc-50/80 dark:border-zinc-700 dark:bg-zinc-900/60">
                        <header class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full {{ $stageClasses }}"></span>
                                <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $stage->name }}</h2>
                            </div>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-zinc-600 shadow-sm dark:bg-zinc-800 dark:text-zinc-300">{{ $stage->leads->count() }}</span>
                        </header>

                        <div class="grid gap-3 overflow-y-auto p-3">
                            @forelse ($stage->leads as $lead)
                                <article class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <a href="{{ route('leads.show', $lead) }}" class="font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400" wire:navigate>{{ $lead->title }}</a>
                                    <p class="mt-1 truncate text-sm text-zinc-500">{{ $lead->company->name }}</p>
                                    <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $lead->formattedExpectedValue() }}</span>
                                        <span class="truncate text-zinc-500">{{ $lead->assignedTo?->name ?? 'Unassigned' }}</span>
                                    </div>

                                    @can('update', $lead)
                                        <form method="POST" action="{{ route('leads.stage.update', $lead) }}" class="mt-4 grid gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                            @csrf
                                            @method('PUT')
                                            <select name="stage_id" aria-label="Move {{ $lead->title }} to stage" class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                                                @foreach ($pipeline->stages as $destination)
                                                    <option value="{{ $destination->id }}" @selected($destination->is($stage))>{{ $destination->name }}</option>
                                                @endforeach
                                            </select>
                                            <input name="loss_reason" aria-label="Loss reason" placeholder="Loss reason if moving to Lost" class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                                            <button type="submit" class="text-start text-xs font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">Move lead →</button>
                                        </form>
                                    @endcan
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700">No leads</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
