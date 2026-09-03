<x-layouts::app :title="__('Pipeline settings')">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div>
            <a href="{{ route('leads.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400" wire:navigate>← Back to pipeline</a>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">Pipeline settings</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Create sales processes with ordered open stages. Won and Lost are added automatically.</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-950 dark:text-white">Sales pipelines</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $pipelines->count() }} configured</p>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($pipelines as $pipeline)
                        <div class="px-5 py-5">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-950 dark:text-white">{{ $pipeline->name }}</h3>
                                @if ($pipeline->is_default)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Default</span>
                                @endif
                            </div>
                            <ol class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                @foreach ($pipeline->stages as $stage)
                                    <li class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $stage->position }}. {{ $stage->name }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </section>

            <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Add pipeline</h2>
                <p class="mt-1 text-sm text-zinc-500">Enter one open stage per line, in order.</p>
                <form method="POST" action="{{ route('pipelines.store') }}" class="mt-5 grid gap-4">
                    @csrf
                    <flux:input name="name" :label="__('Pipeline name')" :value="old('name')" required />
                    <flux:textarea name="stages_text" :label="__('Open stages')" rows="7" placeholder="New&#10;Qualified&#10;Proposal" required>{{ old('stages_text') }}</flux:textarea>
                    @error('stages_text') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    <flux:button type="submit" variant="primary" class="w-full">Create pipeline</flux:button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts::app>
