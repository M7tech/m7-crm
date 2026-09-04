<x-layouts::app :title="__('Automations')">
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-600 dark:text-violet-400">Workflow</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Automations</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Create a follow-up task automatically when a lead enters a selected stage.</p>
            <p class="mt-1 text-sm text-zinc-500">Rules apply to future lead creation and stage changes; they do not create tasks for leads already in that stage.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="grid gap-5">
                @forelse ($rules as $rule)
                    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $rule->name }}</h2>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $rule->is_active ? 'Active' : 'Paused' }}</span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    When a lead enters <strong class="text-zinc-900 dark:text-zinc-100">{{ $rule->stage->pipeline->name }} / {{ $rule->stage->name }}</strong>,
                                    create “{{ $rule->task_title }}” due {{ $rule->due_days === 0 ? 'immediately' : $rule->due_days.' '.\Illuminate\Support\Str::plural('day', $rule->due_days).' later' }}.
                                </p>
                                <p class="mt-1 text-xs text-zinc-500">Priority: {{ ucfirst($rule->priority) }} · Assignee: {{ $rule->assignee_strategy === 'lead_owner' ? 'Lead owner' : 'Unassigned' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('automations.status', $rule) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                                    <flux:button type="submit" variant="filled">{{ $rule->is_active ? 'Pause' : 'Enable' }}</flux:button>
                                </form>
                                <form method="POST" action="{{ route('automations.destroy', $rule) }}" onsubmit="return confirm('Delete this automation? Existing run history will remain available.')">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" variant="danger">Delete</flux:button>
                                </form>
                            </div>
                        </div>
                    </section>
                @empty
                    <section class="rounded-2xl border border-dashed border-zinc-300 bg-white px-5 py-14 text-center dark:border-zinc-700 dark:bg-zinc-900">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">No automation rules yet</h2>
                        <p class="mt-1 text-sm text-zinc-500">Create the first rule using the form.</p>
                    </section>
                @endforelse

                <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <h2 class="font-semibold text-zinc-950 dark:text-white">Recent runs</h2>
                        <p class="mt-1 text-sm text-zinc-500">An audit trail of tasks created by automation.</p>
                    </div>
                    @forelse ($runs as $run)
                        <div class="flex flex-col gap-2 border-b border-zinc-100 px-5 py-4 text-sm last:border-0 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $run->rule->name }}</p>
                                <p class="mt-1 text-zinc-500">
                                    <a class="hover:underline" href="{{ route('leads.show', $run->lead) }}">{{ $run->lead->title }}</a>
                                    @if ($run->task) → <a class="hover:underline" href="{{ route('tasks.show', $run->task) }}">{{ $run->task->title }}</a>@endif
                                </p>
                                @if ($run->error)<p class="mt-1 text-xs text-red-600">{{ $run->error }}</p>@endif
                            </div>
                            <div class="text-start sm:text-end">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $run->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($run->status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">{{ ucfirst($run->status) }}</span>
                                <p class="mt-1 text-xs text-zinc-500">{{ $run->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-zinc-500">No automation has run yet.</p>
                    @endforelse
                </section>
            </div>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">New automation</h2>
                <p class="mt-1 text-sm text-zinc-500">Use <code>{lead}</code> in the task title to insert the lead name. Each workspace can create up to 25 rules.</p>
                <form method="POST" action="{{ route('automations.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="name" :label="__('Rule name')" :value="old('name')" placeholder="Qualified lead follow-up" required />
                    <div>
                        <label for="stage_id" class="mb-2 block text-sm font-medium">When lead enters stage</label>
                        <select id="stage_id" name="stage_id" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach ($pipelines as $pipeline)
                                <optgroup label="{{ $pipeline->name }}">
                                    @foreach ($pipeline->stages as $stage)<option value="{{ $stage->id }}" @selected((int) old('stage_id') === $stage->id)>{{ $stage->name }}</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <flux:input name="task_title" :label="__('Task title')" :value="old('task_title', 'Follow up: {lead}')" maxlength="180" required />
                    <flux:input name="due_days" type="number" min="0" max="365" :label="__('Due after days')" :value="old('due_days', 1)" required />
                    <div>
                        <label for="priority" class="mb-2 block text-sm font-medium">Priority</label>
                        <select id="priority" name="priority" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            @foreach (['low', 'normal', 'high', 'urgent'] as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ ucfirst($priority) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="assignee_strategy" class="mb-2 block text-sm font-medium">Assign task to</label>
                        <select id="assignee_strategy" name="assignee_strategy" required class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                            <option value="lead_owner" @selected(old('assignee_strategy', 'lead_owner') === 'lead_owner')>Lead owner</option>
                            <option value="unassigned" @selected(old('assignee_strategy') === 'unassigned')>Unassigned</option>
                        </select>
                    </div>
                    <flux:button type="submit" variant="primary" class="w-full">Create automation</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
