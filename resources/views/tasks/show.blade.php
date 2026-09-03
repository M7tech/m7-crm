<x-layouts::app :title="$task->title">
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('tasks.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to tasks</a>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $task->title }}</h1>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $task->status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">{{ ucfirst($task->status) }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('tasks.status.update', $task) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="{{ $task->status === 'completed' ? 'pending' : 'completed' }}">
                    <flux:button type="submit">{{ $task->status === 'completed' ? 'Reopen' : 'Complete' }}</flux:button>
                </form>
                <flux:button :href="route('tasks.edit', $task)" variant="primary" wire:navigate>Edit task</flux:button>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <dl class="grid sm:grid-cols-2">
                    @foreach ([
                        'Due' => $task->due_at->timezone($timezone)->format('M j, Y H:i'),
                        'Priority' => ucfirst($task->priority),
                        'Assigned to' => $task->assignedTo?->name ?? 'Unassigned',
                        'Created by' => $task->createdBy?->name ?? 'System',
                        'Related lead' => $task->lead?->title ?? '—',
                        'Company' => $task->lead?->company?->name ?? '—',
                        'Reminder' => $task->reminder_at?->timezone($timezone)->format('M j, Y H:i') ?? '—',
                        'Completed' => $task->completed_at?->timezone($timezone)->format('M j, Y H:i') ?? '—',
                    ] as $label => $value)
                        <div class="border-b border-zinc-100 px-5 py-4 sm:odd:border-e dark:border-zinc-800">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($task->description)
                    <div class="px-5 py-5">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Notes</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $task->description }}</p>
                    </div>
                @endif
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Task history</h2>
                <ol class="mt-5 grid gap-5">
                    @foreach ($task->activities as $activity)
                        <li class="relative border-s-2 border-zinc-200 ps-4 dark:border-zinc-700">
                            <span class="absolute -start-[5px] top-1 size-2 rounded-full bg-emerald-500"></span>
                            <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ $activity->description }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->created_at->timezone($timezone)->format('M j, Y H:i') }}</p>
                        </li>
                    @endforeach
                </ol>
            </aside>
        </div>
    </div>
</x-layouts::app>
