<x-layouts::app :title="__('Add lead')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('leads.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to pipeline</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Add lead</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Create a sales opportunity and place it in a pipeline stage.</p>
        </div>

        @if ($companies->isEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                <h2 class="font-semibold">A company is required</h2>
                <p class="mt-1 text-sm">Create a customer company before adding a lead.</p>
                <flux:button :href="route('companies.index')" class="mt-4" wire:navigate>Go to companies</flux:button>
            </section>
        @else
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <form method="POST" action="{{ route('leads.store') }}" class="grid gap-6">
                    @csrf
                    @include('leads._fields', ['lead' => null])
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <flux:button :href="route('leads.index')" variant="ghost" wire:navigate>Cancel</flux:button>
                        <flux:button type="submit" variant="primary">Create lead</flux:button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layouts::app>
