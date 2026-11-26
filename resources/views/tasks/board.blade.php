<x-layouts::app :title="__('Task board')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-400">{{ __('Follow-ups') }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ __('Task board') }}</h1>
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Drag a card to another column, or change its status with the control on the card.') }}</p>
            </div>
            <flux:button :href="route('tasks.create')" variant="primary" icon="plus" wire:navigate>{{ __('Add task') }}</flux:button>
        </div>

        @include('tasks._views')

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        <div data-task-board data-csrf-token="{{ csrf_token() }}" class="flex flex-col gap-4">
            <p data-board-message class="hidden rounded-xl px-4 py-3 text-sm font-medium" role="status" aria-live="polite"></p>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($columns as $status => $tasks)
                    <section data-task-dropzone data-status="{{ $status }}" class="flex min-h-48 flex-col rounded-2xl border border-zinc-200 bg-zinc-50 p-3 transition dark:border-zinc-700 dark:bg-zinc-900/60" aria-labelledby="column-{{ $status }}">
                        <header class="mb-3 flex items-center justify-between px-1">
                            <h2 id="column-{{ $status }}" class="text-sm font-semibold text-zinc-900 dark:text-white">{{ \App\Models\Task::statusLabel($status) }}</h2>
                            <span data-column-count class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-zinc-600 shadow-sm dark:bg-zinc-800 dark:text-zinc-300">{{ $counts[$status] }}</span>
                        </header>
                        <ol data-task-cards class="flex flex-1 flex-col gap-2">
                            @foreach ($tasks as $task)
                                <li data-task-card data-task-id="{{ $task->id }}" data-current-status="{{ $task->status }}" data-move-url="{{ route('tasks.status.update', $task) }}"
                                    draggable="{{ auth()->user()->can('update', $task) ? 'true' : 'false' }}"
                                    class="cursor-grab rounded-xl border border-zinc-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-800">
                                    <div class="flex items-start justify-between gap-2">
                                        <a href="{{ route('tasks.show', $task) }}" class="min-w-0 text-sm font-semibold text-zinc-950 hover:underline dark:text-white" wire:navigate>{{ $task->title }}</a>
                                        <span @class([
                                            'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                            'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' => $task->priority === 'urgent',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $task->priority === 'high',
                                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' => in_array($task->priority, ['normal', 'low'], true),
                                        ])>{{ __(ucfirst($task->priority)) }}</span>
                                    </div>
                                    @if ($task->contact || $task->company || $task->lead || $task->conversation_id)
                                        <p class="mt-1 truncate text-xs text-zinc-500">{{ collect([$task->contact?->full_name, $task->company?->name, $task->lead?->title, $task->conversation_id ? __('Inbox conversation') : null])->filter()->implode(' · ') }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                        <span @class(['font-medium', 'text-red-600 dark:text-red-400' => $task->isOverdue(), 'text-zinc-600 dark:text-zinc-300' => ! $task->isOverdue()])>{{ $task->due_at->timezone($timezone)->format('M j, H:i') }}</span>
                                        <span class="truncate text-zinc-500">{{ $task->assignedTo?->name ?? __('Unassigned') }}</span>
                                    </div>
                                    @can('update', $task)
                                        <form method="POST" action="{{ route('tasks.status.update', $task) }}" class="mt-2 flex items-center gap-2" data-status-form>
                                            @csrf @method('PUT')
                                            <label for="status-{{ $task->id }}" class="sr-only">{{ __('Move :title to', ['title' => $task->title]) }}</label>
                                            <select id="status-{{ $task->id }}" name="status" class="min-w-0 flex-1 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                                                @foreach (\App\Models\Task::STATUSES as $option)
                                                    <option value="{{ $option }}" @selected($task->status === $option)>{{ \App\Models\Task::statusLabel($option) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700">{{ __('Move') }}</button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ol>
                        <p data-empty-column class="{{ $tasks->isEmpty() ? '' : 'hidden' }} px-1 py-6 text-center text-xs text-zinc-400">{{ __('No tasks here') }}</p>
                        @if ($counts[$status] > $tasks->count())
                            <a href="{{ route('tasks.index', ['scope' => $scope, 'filter' => $status === 'completed' ? 'completed' : 'pending']) }}" class="mt-2 px-1 text-xs font-medium text-blue-600">{{ __('Showing :shown of :total. Open the list for all.', ['shown' => $tasks->count(), 'total' => $counts[$status]]) }}</a>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
