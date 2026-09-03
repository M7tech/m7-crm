<x-layouts::app :title="__('Add task')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('tasks.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to tasks</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Add task</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Schedule a follow-up in {{ $timezone }}.</p>
        </div>
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <form method="POST" action="{{ route('tasks.store') }}" class="grid gap-6">
                @csrf
                @include('tasks._fields', ['task' => null])
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button :href="route('tasks.index')" variant="ghost" wire:navigate>Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Create task</flux:button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
