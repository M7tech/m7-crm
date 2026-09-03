<x-layouts::app :title="__('Tasks')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">Follow-ups</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Tasks</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">Plan sales work and keep overdue follow-ups visible.</p>
            </div>
            <flux:button :href="route('tasks.create')" variant="primary" icon="plus" wire:navigate>Add task</flux:button>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <nav class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Task filters">
            @foreach (['pending' => 'Pending', 'today' => 'Due today', 'overdue' => 'Overdue', 'completed' => 'Completed'] as $key => $label)
                <a href="{{ route('tasks.index', ['filter' => $key]) }}" @class([
                    'rounded-xl border px-4 py-3 transition',
                    'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40' => $filter === $key,
                    'border-zinc-200 bg-white hover:border-emerald-300 dark:border-zinc-700 dark:bg-zinc-900' => $filter !== $key,
                ]) wire:navigate>
                    <span class="text-sm text-zinc-500">{{ $label }}</span>
                    <span class="ms-2 font-semibold text-zinc-950 dark:text-white">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            @forelse ($tasks as $task)
                <div class="grid gap-4 border-b border-zinc-100 px-5 py-4 last:border-0 md:grid-cols-[auto_minmax(0,1fr)_auto] md:items-center dark:border-zinc-800">
                    <form method="POST" action="{{ route('tasks.status.update', $task) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="{{ $task->status === 'completed' ? 'pending' : 'completed' }}">
                        <button type="submit" class="flex size-8 items-center justify-center rounded-full border-2 {{ $task->status === 'completed' ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-zinc-300 text-transparent hover:border-emerald-500 dark:border-zinc-600' }}" aria-label="{{ $task->status === 'completed' ? 'Reopen' : 'Complete' }} {{ $task->title }}">✓</button>
                    </form>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-zinc-950 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400" wire:navigate>{{ $task->title }}</a>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $task->priority === 'urgent',
                                'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $task->priority === 'high',
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => in_array($task->priority, ['normal', 'low']),
                            ])>{{ ucfirst($task->priority) }}</span>
                            @if ($task->isOverdue())
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">Overdue</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm text-zinc-500">{{ $task->lead ? $task->lead->title.' · '.$task->lead->company->name : 'General task' }}</p>
                    </div>
                    <div class="text-sm md:text-end">
                        <p @class(['font-medium', 'text-red-600 dark:text-red-400' => $task->isOverdue(), 'text-zinc-800 dark:text-zinc-200' => ! $task->isOverdue()])>{{ $task->due_at->timezone($timezone)->format('M j, Y H:i') }}</p>
                        <p class="mt-1 text-xs text-zinc-500">{{ $task->assignedTo?->name ?? 'Unassigned' }}</p>
                    </div>
                </div>
            @empty
                <div class="px-5 py-14 text-center">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">No tasks in this view</h2>
                    <p class="mt-1 text-sm text-zinc-500">Create a follow-up or select another filter.</p>
                </div>
            @endforelse
            @if ($tasks->hasPages())
                <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">{{ $tasks->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts::app>
