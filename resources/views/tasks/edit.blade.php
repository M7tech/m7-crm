<x-layouts::app :title="__('Edit task')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to task</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Edit {{ $task->title }}</h1>
        </div>
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <form method="POST" action="{{ route('tasks.update', $task) }}" class="grid gap-6">
                @csrf
                @method('PUT')
                @include('tasks._fields')
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button :href="route('tasks.show', $task)" variant="ghost" wire:navigate>Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save changes</flux:button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::app>
